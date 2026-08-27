<?php

declare(strict_types=1);

abstract class ERP_OAuthProvider
{
	private ?string $cachedAccessToken = null;
	private ?int $cachedAccessTokenExpiresAt = null;

	private array $_error = array();

	abstract protected function requestAccessToken(): array|false;

	# --------------------
	# get the accesstoken for M365
	public function getAccessToken(): string|false
	{
		if ($this->cachedAccessToken && $this->cachedAccessTokenExpiresAt)
		{
			// Refresh one minute before expiry.
			if ( time() < ( $this->cachedAccessTokenExpiresAt - 60 ) )
			{
				return( $this->cachedAccessToken );
			}
		}

		$tokenResponse = $this->requestAccessToken();

		if ( $tokenResponse === FALSE )
		{
			return( FALSE );
		}

		if ( !isset( $tokenResponse[ 'access_token' ], $tokenResponse[ 'expires_in' ] ) )
		{
			$this->setError( 'OAuth token response does not contain the expected access_token and expires_in values.' );
			return( FALSE );
		}

		$this->cachedAccessToken = $tokenResponse[ 'access_token' ];
		$this->cachedAccessTokenExpiresAt = time() + (int)( $tokenResponse[ 'expires_in' ] ?? 3599 );

		return( $this->cachedAccessToken );
	}

	# --------------------
	# Return an XOAUTH2 formatted auth string
	public function createXoauth2String( string $mailbox ): string|false
	{
		$accessToken = $this->getAccessToken();

		if ( $accessToken === FALSE )
		{
			return( $accessToken );
		}

		return( base64_encode(
			'user=' . $mailbox . "\x01" .
			'auth=Bearer ' . $accessToken . "\x01\x01"
		) );
	}

	# --------------------
	# Add an error to _error 
	protected function setError( string $error ): void
	{
		$this->_error[] = $error;
	}

	# --------------------
	# Return the first error in _error
	public function getError(): ?string
	{
		return( array_shift( $this->_error ) );
	}
}

class ERP_Microsoft365OAuthProvider extends ERP_OAuthProvider
{
	private const DEFAULT_SCOPE = 'https://outlook.office365.com/.default';

	private GuzzleHttp\ClientInterface $httpClient;

	private bool $UseClientSecret = FALSE;
	private bool $UseCertificateCredential = FALSE;

	private ?array $certificateData = null;

	public function __construct(
		private readonly string $tenantId,
		private readonly string $clientId,
		private readonly string $clientSecret = '',
		private readonly string $pfxPath = '',
		private readonly string $pfxPassword = '',
		private readonly string $scope = self::DEFAULT_SCOPE,
		?GuzzleHttp\ClientInterface $httpClient = null
	)
	{
		$this->httpClient = $httpClient ?? new GuzzleHttp\Client([
			'timeout' => 30,
			'connect_timeout' => 10,
		]);

		$hasClientSecret = (bool) ( !empty( $clientSecret ) );
		$hasCertificate = (bool) ( !empty( $pfxPath ) && !empty( $pfxPassword ) );

		if ( $hasClientSecret && $hasCertificate )
		{
			$this->setError( 'Configure either a client secret or a PFX certificate, but not both.' );
			return;
		}

		if ( $hasClientSecret )
		{
			$this->UseClientSecret = TRUE;
		}
		elseif ( $hasCertificate )
		{
			$this->UseCertificateCredential = TRUE;
		}
		else
		{
			$this->setError( 'clientSecret or pfxPath + pfxPassword is missing.' );
			return;
		}
	}

	# --------------------
	# Request accesstoken from M365
	protected function requestAccessToken(): array|false
	{
		$tokenEndpoint = $this->getTokenEndpoint();

		$postData = [
			'client_id'     => $this->clientId,
			'scope'         => $this->scope,
			'grant_type'    => 'client_credentials',
		];

		if ( $this->UseClientSecret === TRUE )
		{
			$postData[ 'client_secret' ] = $this->clientSecret;
		}
		elseif ( $this->UseCertificateCredential === TRUE )
		{
			$createClientAssertion = $this->createClientAssertion();
			if ( $createClientAssertion === FALSE)
			{
				return( $createClientAssertion );
			}
			
			$postData[ 'client_assertion_type' ] = 'urn:ietf:params:oauth:client-assertion-type:jwt-bearer';
			$postData[ 'client_assertion' ] = $createClientAssertion;
		}
		else
		{
			$this->setError( 'No Microsoft authentication method is configured.' );
			return( FALSE );
		}

		try
		{
			$response = $this->httpClient->request( 'POST', $tokenEndpoint, [
				'form_params'     => $postData,
				'allow_redirects' => FALSE,
				'headers'         => [
					'Accept' => 'application/json',
				],
			]);
		}
		catch ( GuzzleHttp\Exception\GuzzleException $e )
		{
			$errorDetails = $e->getMessage();

			if (
				$e instanceof GuzzleHttp\Exception\RequestException &&
				$e->getResponse() !== null
			)
			{
				$responseBody = (string)$e->getResponse()->getBody();

				if ( $responseBody !== '' )
				{
					$errorDetails .= ' Response: ' . $responseBody;
				}
			}

			$this->setError(
				'Failed to request Microsoft 365 access token. ' . $errorDetails . 
				' ERRORCODE: ' . (int)$e->getCode()
			);
			return( FALSE );
		}

		$body = (string)$response->getBody();
		$data = json_decode($body, true);

		if ( !is_array( $data ) )
		{
			$this->setError( 'Microsoft token response was not valid JSON: ' . $body );
			return( FALSE );
		}

		if ( empty( $data[ 'access_token' ] ) )
		{
			$this->setError( 'Microsoft token response did not contain an access_token: ' . $body );
			return( FALSE );
		}

		return( $data );
	}

	# --------------------
	# Create the ClientAssertion
	private function createClientAssertion(): string|false
	{
		$certificateData = $this->readPfx();
		if ( $certificateData === FALSE )
		{
			return( $certificateData );
		}

		$now = time();

		$payload = [
			'aud' => $this->getTokenEndpoint(),
			'iss' => $this->clientId,
			'sub' => $this->clientId,
			'jti' => self::createGuid(),
			'nbf' => $now,
			'iat' => $now,
			'exp' => $now + 600,
		];

		/*
		 * Microsoft's current certificate assertion documentation specifies:
		 * - alg: PS256
		 * - typ: JWT
		 * - x5t#S256: base64url-encoded SHA-256 thumbprint of the DER certificate
		 */
		$headers = [
			'typ' => 'JWT',
			'x5t#S256' => self::base64UrlEncode(
				hash( 'sha256', $certificateData[ 'certificateDer' ], TRUE )
			),
		];

		return( Firebase\JWT\JWT::encode(
			$payload,
			$certificateData[ 'privateKeyPem' ],
			'PS256',
			null,
			$headers
		) );
	}

	# --------------------
	# Read the pfx certificate
	private function readPfx(): array|false
	{
		if ( $this->certificateData !== null )
		{
			return( $this->certificateData );
		}

		if ( !is_file( $this->pfxPath ) )
		{
			$this->setError( 'PFX file not found: ' . $this->pfxPath );
			return( FALSE );
		}

		$pfxContents = file_get_contents( $this->pfxPath );

		if ( $pfxContents === FALSE )
		{
			$this->setError( 'Could not read PFX file: ' . $this->pfxPath );
			return( FALSE );
		}

		$certificates = [];

		if ( !openssl_pkcs12_read( $pfxContents, $certificates, $this->pfxPassword ) )
		{
			$this->setError( 'Could not read PFX file. Check the PFX password.' );
			return( FALSE );
		}

		if ( empty( $certificates[ 'pkey' ] ) )
		{
			$this->setError( 'PFX file does not contain a private key.' );
			return( FALSE );
		}

		if ( empty( $certificates[ 'cert' ] ) )
		{
			$this->setError( 'PFX file does not contain a certificate.' );
			return( FALSE );
		}

		$certificateDer = self::pemCertificateToDer( $certificates[ 'cert' ] );
		if ( $certificateDer === FALSE )
		{
			return( $certificateDer );
		}

		$this->certificateData = [
			'privateKeyPem' => $certificates[ 'pkey' ],
			'certificatePem' => $certificates[ 'cert' ],
			'certificateDer' => $certificateDer,
		];

		return( $this->certificateData );
	}

	# --------------------
	# Get token endpoint for Microsoft365
	private function getTokenEndpoint(): string
	{
		return( 'https://login.microsoftonline.com/' .
			rawurlencode($this->tenantId) .
			'/oauth2/v2.0/token'
		);
	}

	# --------------------
	# Pem certificate to Der
	private function pemCertificateToDer( string $certificatePem ): string|false
	{
		$certificateDer = preg_replace(
			'/-----BEGIN CERTIFICATE-----|-----END CERTIFICATE-----|\s/',
			'',
			$certificatePem
		);

		if ( !is_string( $certificateDer ) || $certificateDer === '' )
		{
			$this->setError( 'Could not parse PEM certificate.' );
			return( FALSE );
		}

		$decoded = base64_decode( $certificateDer, TRUE );

		if ( $decoded === FALSE )
		{
			$this->setError( 'Could not decode PEM certificate.' );
			return( FALSE );
		}

		return( $decoded );
	}

	# --------------------
	# URL safe base64_encode
	private static function base64UrlEncode( string $data ): string
	{
		return( rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' ) );
	}

	# --------------------
	# Creates a GUID
	private static function createGuid(): string
	{
		$data = random_bytes(16);

		$data[ 6 ] = chr( ( ord( $data[ 6 ] ) & 0x0f ) | 0x40 );
		$data[ 8 ] = chr( ( ord( $data[ 8 ] ) & 0x3f ) | 0x80 );

		return( vsprintf( '%s%s-%s-%s-%s-%s%s%s', str_split( bin2hex( $data ), 4 ) ) );
	}
}

class ERP_GoogleOAuthProvider extends ERP_OAuthProvider
{
	private const DEFAULT_SCOPE = 'https://www.googleapis.com/auth/gmail.imap_admin';

	public function __construct(
		private readonly array|string $serviceAccountCredentials,
		private readonly string $mailbox,
		private readonly string $scope = self::DEFAULT_SCOPE
	)
	{
		if ( trim( $this->mailbox ) === '' )
		{
			$this->setError( 'Google Workspace mailbox cannot be empty.' );
			return;
		}

		if (
			is_string( $this->serviceAccountCredentials ) &&
			!is_file( $this->serviceAccountCredentials )
		)
		{
			$this->setError(
				'Google service-account JSON file was not found: ' .
				$this->serviceAccountCredentials
			);
			return;
		}
	}

	# --------------------
	# Request access token from Google
	protected function requestAccessToken(): array|false
	{
		$credentials = new Google\Auth\Credentials\ServiceAccountCredentials(
			$this->scope,
			$this->serviceAccountCredentials,
			trim( $this->mailbox )
		);

		try
		{
			$tokenResponse = $credentials->fetchAuthToken();
		}
		catch ( Throwable $e )
		{
			$this->setError(
				'Failed to request Google Workspace access token: ' .
				$e->getMessage() . 
				' ERRORCODE: ' . (int)$e->getCode()
			);
			return( FALSE );
		}

		if ( !is_array( $tokenResponse ) )
		{
			$this->setError( 'Google token response was not an array.' );
			return( FALSE );
		}

		if ( empty( $tokenResponse[ 'access_token' ] ) )
		{
			$this->setError( 'Google token response did not contain an access_token.' );
			return( FALSE );
		}

		return( $tokenResponse );
	}
}
?>
