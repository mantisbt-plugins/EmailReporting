$Shell = $Host.UI.RawUI
$Shell.WindowTitle="EmailReporting app creation script"

#Requires -Version 5.1

<#
.SYNOPSIS
    Create an Entra App for IMAP/POP OAuth app-only access and assign it to a single or multiple mailboxes.

.DESCRIPTION
    This script:
    - Creates an Entra App Registration
    - Creates the Service Principal / Enterprise App
    - Adds Office 365 Exchange Online application permission: IMAP.AccessAsApp
    - Grants admin consent via appRoleAssignment
    - Creates an Exchange Online ServicePrincipal reference
    - Grants FullAccess to a single mailbox for that service principal

.ASSUMPTIONS
    - Microsoft Graph PowerShell is already connected
    - Exchange Online PowerShell is already connected
    - You have sufficient Entra and Exchange permissions
    - You want to use POP or IMAP with OAuth client credentials
#>
<#
Office 365 Exchange Online
└─ Application Permission
   └─ IMAP.AccessAsApp
   └─ POP.AccessAsApp
   
https://outlook.office365.com/.default
#>

# -----------------------------
# Variables
# -----------------------------

$AppDisplayName = "EmailReporting Mailbox App"
$Mailboxes      = @(
    "emailreporting@mantisbt.org",
    "another@nonexistent.emailaddress"
)

$CreateClientSecret = $true
$SecretDisplayName  = "EmailReporting Mailbox Secret"
$SecretValidMonths  = 12

$CreateCertificateCredential = $true
$CertificateSubject          = "CN=$AppDisplayName"
$CertificateValidMonths      = 12
$CertificatePassword         = Read-Host "Enter password for PFX export" -AsSecureString

$CertificateOutputPath       = "$PSScriptRoot\$($AppDisplayName -replace '[\\/:*?""<>|]', '_')"
$CerPath                     = "$CertificateOutputPath.cer"
$PfxPath                     = "$CertificateOutputPath.pfx"
$PemPath                     = "$CertificateOutputPath.pem"


# Office 365 Exchange Online resource app.
$ExchangeOnlineResourceAppId = "00000002-0000-0ff1-ce00-000000000000"

# Required permissions
$ExchangePermissionValueIMAP = "IMAP.AccessAsApp"
$ExchangePermissionValuePOP  = "POP.AccessAsApp"

# -----------------------------
# Helper functions
# -----------------------------

function Wait-ForObject {
    param(
        [Parameter(Mandatory)]
        [scriptblock]$ScriptBlock,

        [int]$TimeoutSeconds = 60,
        [int]$DelaySeconds = 3,
        [string]$WaitingFor = "object"
    )

    $stopwatch = [System.Diagnostics.Stopwatch]::StartNew()

    do {
        $result = & $ScriptBlock

        if ($result) {
            return $result
        }

        Start-Sleep -Seconds $DelaySeconds
    }
    while ($stopwatch.Elapsed.TotalSeconds -lt $TimeoutSeconds)

    throw "Timed out waiting for $WaitingFor."
}

function Test-CommandParameter {
    param(
        [Parameter(Mandatory)]
        [string]$CommandName,

        [Parameter(Mandatory)]
        [string]$ParameterName
    )

    $command = Get-Command -Name $CommandName -ErrorAction Stop
    return $command.Parameters.ContainsKey($ParameterName)
}

# -----------------------------
# Basic checks
# -----------------------------

$Module=Get-InstalledModule -Name Microsoft.Graph -ErrorAction SilentlyContinue
if($Module.count -eq 0)
{
    Write-Host Installing MSGraph. -ForegroundColor Green
    Install-Module Microsoft.Graph -AllowClobber -Scope CurrentUser
}
$Module=Get-InstalledModule -Name ExchangeOnlineManagement -ErrorAction SilentlyContinue
if($Module.count -eq 0)
{
    Write-Host ExchangeOnlineManagement. -ForegroundColor Green
    Install-Module ExchangeOnlineManagement -AllowClobber -Scope CurrentUser
}

Write-Host "Checking Microsoft Graph connection..." -ForegroundColor Cyan

$MgContext = Get-MgContext

if (-not $MgContext) {
    $GraphScopes = @(
        "Directory.Read.All",
        "Application.ReadWrite.All",
        "AppRoleAssignment.ReadWrite.All"

#        "RoleManagement.ReadWrite.Directory"
    )
    Connect-MgGraph -Scopes $GraphScopes -ContextScope Process -NoWelcome
}

$MgContext = Get-MgContext

if (-not $MgContext) {
    throw "Microsoft Graph is not connected. Connect first using Connect-MgGraph."
}

Write-Host "Connected to tenant: $($MgContext.TenantId)" -ForegroundColor Green

Write-Host "Checking Microsoft Exchange connection..." -ForegroundColor Cyan

if(-not (Get-ConnectionInformation | Where-Object {$_.State -eq 'Connected' -and -not $_.IsEopSession})) {
    Connect-ExchangeOnline -ShowBanner:$false
}

$EXOConnection = Get-ConnectionInformation | Where-Object {$_.State -eq 'Connected' -and -not $_.IsEopSession}

if(-not ($EXOConnection)) {
    throw "Microsoft Exchange is not connected. Connect first using Connect-ExchangeOnline."
}

Write-Host "Connected to EXO: $(($EXOConnection).UserPrincipalName)" -ForegroundColor Green

$Mailboxes = @($Mailboxes)
foreach ($Mailbox in $Mailboxes) {
    Write-Host "Checking mailbox: $Mailbox" -ForegroundColor Cyan

    try {
        $TargetMailbox = Get-EXOMailbox -Identity $Mailbox -ErrorAction Stop
    }
    catch {
        try {
            $TargetMailbox = Get-Mailbox -Identity $Mailbox -ErrorAction Stop
        }
        catch {
            throw "Mailbox '$Mailbox' could not be found."
        }
    }

    Write-Host "Mailbox found: $($TargetMailbox.PrimarySmtpAddress)" -ForegroundColor Green
}

# -----------------------------
# Resolve Office 365 Exchange Online service principal
# -----------------------------

Write-Host "Resolving Office 365 Exchange Online service principal..." -ForegroundColor Cyan

$ExchangeOnlineServicePrincipal = Get-MgServicePrincipal `
    -Filter "appId eq '$ExchangeOnlineResourceAppId'" `
    -ErrorAction SilentlyContinue |
    Select-Object -First 1

if (-not $ExchangeOnlineServicePrincipal) {
    $ExchangeOnlineServicePrincipal = Get-MgServicePrincipal `
        -Filter "displayName eq 'Office 365 Exchange Online'" `
        -ErrorAction Stop |
        Select-Object -First 1
}

if (-not $ExchangeOnlineServicePrincipal) {
    throw "Could not find the Office 365 Exchange Online service principal."
}

Write-Host "Found resource: $($ExchangeOnlineServicePrincipal.DisplayName)" -ForegroundColor Green
Write-Host "Resource AppId: $($ExchangeOnlineServicePrincipal.AppId)" -ForegroundColor Green

# -----------------------------
# Resolve IMAP.AccessAsApp app role
# -----------------------------

Write-Host "Resolving application permission: $ExchangePermissionValueIMAP" -ForegroundColor Cyan

$ImapAccessAsAppRole = $ExchangeOnlineServicePrincipal.AppRoles |
    Where-Object {
        $_.Value -eq $ExchangePermissionValueIMAP -and
        $_.AllowedMemberTypes -contains "Application" -and
        $_.IsEnabled -eq $true
    } |
    Select-Object -First 1

if (-not $ImapAccessAsAppRole) {
    throw "Could not find application permission '$ExchangePermissionValueIMAP' on Office 365 Exchange Online."
}

Write-Host "Found app role: $($ImapAccessAsAppRole.Value)" -ForegroundColor Green

# -----------------------------
# Resolve POP.AccessAsApp app role
# -----------------------------

Write-Host "Resolving application permission: $ExchangePermissionValuePOP" -ForegroundColor Cyan

$PopAccessAsAppRole = $ExchangeOnlineServicePrincipal.AppRoles |
    Where-Object {
        $_.Value -eq $ExchangePermissionValuePOP -and
        $_.AllowedMemberTypes -contains "Application" -and
        $_.IsEnabled -eq $true
    } |
    Select-Object -First 1

if (-not $PopAccessAsAppRole) {
    throw "Could not find application permission '$ExchangePermissionValuePOP' on Office 365 Exchange Online."
}

Write-Host "Found app role: $($PopAccessAsAppRole.Value)" -ForegroundColor Green

# -----------------------------
# Create or update Entra App Registration
# -----------------------------

Write-Host "Checking app registration: $AppDisplayName" -ForegroundColor Cyan

$Application = Get-MgApplication `
    -Filter "displayName eq '$AppDisplayName'" `
    -ErrorAction Stop |
    Select-Object -First 1

$RequiredResourceAccess = @(
    @{
        ResourceAppId = $ExchangeOnlineServicePrincipal.AppId
        ResourceAccess = @(
            @{
                Id   = $ImapAccessAsAppRole.Id
                Type = "Role"
            }
            @{
                Id   = $PopAccessAsAppRole.Id
                Type = "Role"
            }
        )
    }
)

if (-not $Application) {
    Write-Host "Creating app registration..." -ForegroundColor Cyan

    $Application = New-MgApplication `
        -DisplayName $AppDisplayName `
        -SignInAudience "AzureADMyOrg" `
        -RequiredResourceAccess $RequiredResourceAccess `
        -ErrorAction Stop

    $Application = Wait-ForObject `
        -WaitingFor "application" `
        -ScriptBlock {
            Get-MgApplication `
                -Filter "appId eq '$($Application.AppId)'" `
                -ErrorAction SilentlyContinue |
                Select-Object -First 1
        }

    Write-Host "Created app registration." -ForegroundColor Green
}
else {
    Write-Host "App registration already exists." -ForegroundColor Yellow

    Write-Host "Ensuring required API permission is configured on app registration..." -ForegroundColor Cyan

    Update-MgApplication `
        -ApplicationId $Application.Id `
        -RequiredResourceAccess $RequiredResourceAccess `
        -ErrorAction Stop

    Write-Host "Updated app registration API permissions." -ForegroundColor Green
}

# -----------------------------
# Create Service Principal / Enterprise App
# -----------------------------

Write-Host "Checking service principal / enterprise app..." -ForegroundColor Cyan

$ClientServicePrincipal = Get-MgServicePrincipal `
    -Filter "appId eq '$($Application.AppId)'" `
    -ErrorAction Stop |
    Select-Object -First 1

if (-not $ClientServicePrincipal) {
    Write-Host "Creating service principal / enterprise app..." -ForegroundColor Cyan

    $ClientServicePrincipal = New-MgServicePrincipal `
        -AppId $Application.AppId `
        -ErrorAction Stop

    $ClientServicePrincipal = Wait-ForObject `
        -WaitingFor "client service principal" `
        -ScriptBlock {
            Get-MgServicePrincipal `
                -Filter "appId eq '$($Application.AppId)'" `
                -ErrorAction SilentlyContinue |
                Select-Object -First 1
        }

    Write-Host "Created service principal." -ForegroundColor Green
}
else {
    Write-Host "Service principal already exists." -ForegroundColor Yellow
}

# -----------------------------
# Grant admin consent / app role assignment
# -----------------------------

Write-Host "Pause for Entra to propagate" -ForegroundColor Yellow
Start-Sleep -Seconds 10

Write-Host "Checking app role assignment / admin consent..." -ForegroundColor Cyan

$ExistingAppRoleAssignments = Get-MgServicePrincipalAppRoleAssignment `
    -ServicePrincipalId $ClientServicePrincipal.Id `
    -All `
    -ErrorAction Stop

$ExistingAppRoleAssignment = $ExistingAppRoleAssignments |
    Where-Object {
        $_.ResourceId -eq $ExchangeOnlineServicePrincipal.Id -and
        $_.AppRoleId -eq $ImapAccessAsAppRole.Id
    } |
    Select-Object -First 1

if (-not $ExistingAppRoleAssignment) {
    Write-Host "Granting admin consent for $ExchangePermissionValueIMAP..." -ForegroundColor Cyan

    New-MgServicePrincipalAppRoleAssignment `
        -ServicePrincipalId $ClientServicePrincipal.Id `
        -PrincipalId $ClientServicePrincipal.Id `
        -ResourceId $ExchangeOnlineServicePrincipal.Id `
        -AppRoleId $ImapAccessAsAppRole.Id `
        -ErrorAction Stop |
        Out-Null

    Write-Host "Granted application permission: $ExchangePermissionValueIMAP" -ForegroundColor Green
}
else {
    Write-Host "Application permission already granted: $ExchangePermissionValueIMAP" -ForegroundColor Yellow
}

$ExistingAppRoleAssignment = $ExistingAppRoleAssignments |
    Where-Object {
        $_.ResourceId -eq $ExchangeOnlineServicePrincipal.Id -and
        $_.AppRoleId -eq $PopAccessAsAppRole.Id
    } |
    Select-Object -First 1

if (-not $ExistingAppRoleAssignment) {
    Write-Host "Granting admin consent for $ExchangePermissionValuePOP..." -ForegroundColor Cyan

    New-MgServicePrincipalAppRoleAssignment `
        -ServicePrincipalId $ClientServicePrincipal.Id `
        -PrincipalId $ClientServicePrincipal.Id `
        -ResourceId $ExchangeOnlineServicePrincipal.Id `
        -AppRoleId $PopAccessAsAppRole.Id `
        -ErrorAction Stop |
        Out-Null

    Write-Host "Granted application permission: $ExchangePermissionValuePOP" -ForegroundColor Green
}
else {
    Write-Host "Application permission already granted: $ExchangePermissionValuePOP" -ForegroundColor Yellow
}

# -----------------------------
# Create client / certificate secret
# -----------------------------

$ClientSecret = $null

if ($CreateClientSecret) {
    Write-Host "Creating client secret..." -ForegroundColor Cyan

    $PasswordCredential = @{
        DisplayName = $SecretDisplayName
        EndDateTime = (Get-Date).AddMonths($SecretValidMonths)
    }

    try {
        $ClientSecret = Add-MgApplicationPassword `
            -ApplicationId $Application.Id `
            -PasswordCredential $PasswordCredential `
            -ErrorAction Stop

        Write-Host "Client secret created. Save the secret value now. It cannot be retrieved later." -ForegroundColor Yellow
    }
    catch {
        Write-Host "Client secret creation failed." -ForegroundColor Red
        Write-Host $_.Exception.Message -ForegroundColor Red
    }
}

$CertificateCredential = $null

if ($CreateCertificateCredential) {
    if (Test-Path $PfxPath) {
        Write-Host "Existing Certificate found: $PfxPath" -ForegroundColor Cyan

        $Certificate = Get-PfxData -FilePath $PfxPath -Password $CertificatePassword | select -expandproperty EndEntityCertificates

        if (-not $Certificate) {
            throw "Certificate import failed. Please check the password."
        }
        else {
            Write-Host "Certificate imported" -ForegroundColor Green
        }
    }
    else {
        Write-Host "Creating self-signed certificate..." -ForegroundColor Cyan

        $Certificate = New-SelfSignedCertificate `
            -Subject $CertificateSubject `
            -CertStoreLocation "Cert:\CurrentUser\My" `
            -KeyExportPolicy Exportable `
            -KeySpec Signature `
            -KeyLength 2048 `
            -KeyAlgorithm RSA `
            -HashAlgorithm SHA256 `
            -NotAfter (Get-Date).AddMonths($CertificateValidMonths)

        Export-PfxCertificate `
            -Cert $Certificate `
            -FilePath $PfxPath `
            -Password $CertificatePassword `
            -Force |
            Out-Null

        Write-Host "Certificate exported:" -ForegroundColor Green
    }

    Export-Certificate `
        -Cert $Certificate `
        -FilePath $CerPath `
        -Force |
        Out-Null

    if(($PSVersionTable::PSVersion.Major) -ge 7) {
        $rsa = [System.Security.Cryptography.X509Certificates.RSACertificateExtensions]::GetRSAPrivateKey($Certificate)
        $keyBytes = $rsa.ExportPkcs8PrivateKey()
        $pem = -join [System.Security.Cryptography.PemEncoding]::Write(
            "PRIVATE KEY",
            $keyBytes
        )
        Set-Content -Path $PemPath -Value $pem -Encoding ascii
    }
    elseif (Get-Command openssl -ErrorAction SilentlyContinue) {
        $PlainPassword = [System.Net.NetworkCredential]::new(
            '',
            $CertificatePassword
            ).Password
        openssl pkcs12 -in $PfxPath -nocerts -nodes -out $PemPath -passin "pass:$PlainPassword"
    }

    Write-Host "CER: $CerPath"
    Write-Host "PFX: $PfxPath"
    if((($PSVersionTable::PSVersion.Major) -ge 7) -or (Get-Command openssl -ErrorAction SilentlyContinue)) {
        Write-Host "PEM: $PemPath"
    }

    $RawCertificate = [System.IO.File]::ReadAllBytes($CerPath)

    $KeyCredential = @{
        Type                = "AsymmetricX509Cert"
        Usage               = "Verify"
        Key                 = $RawCertificate
        DisplayName         = $AppDisplayName
        StartDateTime       = $Certificate.NotBefore
        EndDateTime         = $Certificate.NotAfter
        CustomKeyIdentifier = [System.Convert]::FromBase64String($Certificate.Thumbprint)
    }

    Write-Host "Adding certificate credential to app registration..." -ForegroundColor Cyan

    $ExistingApplication = Get-MgApplication -ApplicationId $Application.Id -ErrorAction Stop

    # Check whether certificate already exists
    $existingCert = $ExistingApplication.KeyCredentials | Where-Object {
        $_.CustomKeyIdentifier -and
        ([System.Convert]::ToBase64String($_.CustomKeyIdentifier).ToUpperInvariant() -eq $Certificate.Thumbprint.ToUpperInvariant())
    }

    if ($existingCert) {
        Write-Host "Certificate already exists on app registration" -ForegroundColor Yellow
    }
    else {
        $UpdatedKeyCredentials = @()

        if ($ExistingApplication.KeyCredentials) {
            $UpdatedKeyCredentials += $ExistingApplication.KeyCredentials
        }

        $UpdatedKeyCredentials += $KeyCredential

        Update-MgApplication `
            -ApplicationId $Application.Id `
            -KeyCredentials $UpdatedKeyCredentials `
            -ErrorAction Stop

        Write-Host "Certificate credential added." -ForegroundColor Green
    }

    $CertificateCredential = [PSCustomObject]@{
        Thumbprint = $Certificate.Thumbprint
        CerPath    = $CerPath
        PfxPath    = $PfxPath
        NotAfter   = $Certificate.NotAfter
    }
}

# -----------------------------
# Create Exchange Online ServicePrincipal reference
# -----------------------------

Write-Host "Checking Exchange Online service principal reference..." -ForegroundColor Cyan

$ExchangeServicePrincipal = Get-ServicePrincipal -ErrorAction SilentlyContinue |
    Where-Object {
        $_.AppId -eq $Application.AppId -or
        $_.ObjectId -eq $ClientServicePrincipal.Id
    } |
    Select-Object -First 1

if (-not $ExchangeServicePrincipal) {
    Write-Host "Creating Exchange Online service principal reference..." -ForegroundColor Cyan

    $NewServicePrincipalParams = @{
        AppId       = $Application.AppId
        DisplayName = $AppDisplayName
        ErrorAction = "Stop"
    }

    if (Test-CommandParameter -CommandName "New-ServicePrincipal" -ParameterName "ObjectId") {
        $NewServicePrincipalParams.ObjectId = $ClientServicePrincipal.Id
    }
    elseif (Test-CommandParameter -CommandName "New-ServicePrincipal" -ParameterName "ServiceId") {
        $NewServicePrincipalParams.ServiceId = $ClientServicePrincipal.Id
    }
    else {
        throw "New-ServicePrincipal has neither -ObjectId nor -ServiceId in this session."
    }

    New-ServicePrincipal @NewServicePrincipalParams | Out-Null

    Start-Sleep -Seconds 5

    $ExchangeServicePrincipal = Get-ServicePrincipal -ErrorAction SilentlyContinue |
        Where-Object {
            $_.AppId -eq $Application.AppId -or
            $_.ObjectId -eq $ClientServicePrincipal.Id
        } |
        Select-Object -First 1

    if (-not $ExchangeServicePrincipal) {
        throw "Exchange Online service principal reference was created, but could not be resolved afterwards."
    }

    Write-Host "Created Exchange Online service principal reference." -ForegroundColor Green
}
else {
    Write-Host "Exchange Online service principal reference already exists." -ForegroundColor Yellow
}

# -----------------------------
# Grant mailbox access
# -----------------------------
# This is intentionally written with fallback candidates because Exchange Online
# service principal resolution can differ depending on cmdlet version / tenant behavior.
#
# The goal is to grant FullAccess to only the target mailbox.

Write-Host "Granting mailbox permission to service principal..." -ForegroundColor Cyan

$UserCandidates = @(
    $ExchangeServicePrincipal.Identity,
    $ExchangeServicePrincipal.ObjectId,
    $ClientServicePrincipal.Id,
    $Application.AppId,
    $AppDisplayName
) |
    Where-Object { -not [string]::IsNullOrWhiteSpace($_) } |
    Select-Object -Unique

$MailboxPermissionAdded = $false
$MailboxPermissionErrors = @()

foreach ($Mailbox in $Mailboxes) {
    foreach ($UserCandidate in $UserCandidates) {
        Write-Host "Trying Add-MailboxPermission -User '$UserCandidate'..." -ForegroundColor DarkCyan

        try {
            $ExistingMailboxPermission = Get-MailboxPermission `
                -Identity $Mailbox `
                -ErrorAction Stop |
                Where-Object {
                    $_.User.ToString() -eq $UserCandidate -and
                    $_.AccessRights -contains "FullAccess" -and
                    $_.IsInherited -eq $false
                }

            if ($ExistingMailboxPermission) {
                Write-Host "Mailbox permission already exists for '$UserCandidate'." -ForegroundColor Yellow
                $MailboxPermissionAdded = $true
                break
            }

            Add-MailboxPermission `
                -Identity $Mailbox `
                -User $UserCandidate `
                -AccessRights FullAccess `
                -ErrorAction Stop |
                Out-Null

            Write-Host "Mailbox permission granted using -User '$UserCandidate'." -ForegroundColor Green
            $MailboxPermissionAdded = $true
            break
        }
        catch {
            $MailboxPermissionErrors += [PSCustomObject]@{
                UserCandidate = $UserCandidate
                Error         = $_.Exception.Message
            }
        }
    }

    if (-not $MailboxPermissionAdded) {
        Write-Warning "Could not grant mailbox permission using any detected service principal identifier."
        Write-Warning "Review the errors below and test manually with the ExchangeServicePrincipal identity."

        $MailboxPermissionErrors | Format-Table -AutoSize

        throw "Mailbox permission assignment failed."
    }
}

# -----------------------------
# Output result
# -----------------------------

Write-Host ""
Write-Host "Completed." -ForegroundColor Green
Write-Host ""

$Result = [PSCustomObject]@{
    AppDisplayName                 = $AppDisplayName
    TenantId                       = $MgContext.TenantId
    ClientId                       = $Application.AppId
    ApplicationObjectId            = $Application.Id
    ServicePrincipalObjectId       = $ClientServicePrincipal.Id
    ExchangeServicePrincipalId     = $ExchangeServicePrincipal.Identity
    ExchangeServicePrincipalAppId  = $ExchangeServicePrincipal.AppId
    ExchangeServicePrincipalObjId  = $ExchangeServicePrincipal.ObjectId
    Mailboxes                      = $Mailboxes -join ", "
    PermissionIMAP                 = $ExchangePermissionValueIMAP
    PermissionPOP                  = $ExchangePermissionValuePOP
    TokenScopeForPHP               = "https://outlook.office365.com/.default"
    MailServer                     = "outlook.office365.com"
    ImapPort                       = 993
    PopPort                        = 995
    ClientSecretCreated            = [bool]$ClientSecret
    ClientSecretValue              = if ($ClientSecret) { $ClientSecret.SecretText } else { $null }
    ClientSecretExpires            = if ($ClientSecret) { $ClientSecret.EndDateTime } else { $null }
    CertificateCredentialCreated   = [bool]$CertificateCredential
    CertificateThumbprint          = if ($CertificateCredential) { $CertificateCredential.Thumbprint } else { $null }
    CertificatePfxPath             = if ($CertificateCredential) { $CertificateCredential.PfxPath } else { $null }
    CertificateExpires             = if ($CertificateCredential) { $CertificateCredential.NotAfter } else { $null }
}

$Result | Format-List

if((($PSVersionTable::PSVersion.Major) -lt 7) -and -not (Get-Command openssl -ErrorAction SilentlyContinue)) {
    Write-Host ""
    Write-Host "Powershell 7 is able to write .pem files. Powershell 5 is not." -ForegroundColor Cyan
    Write-Host "You will need to use openssl or a tool of your choice to get the plain text private key." -ForegroundColor Cyan
    Write-Host "Command: openssl pkcs12 -in ""$PfxPath"" -nocerts -nodes -out ""$PemPath"""
    Write-Host ""
}

Write-Host "`nPress Enter to return..."
[void][System.Console]::ReadLine()

