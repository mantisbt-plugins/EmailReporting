<?php

declare(strict_types=1);

abstract class ERP_OAuthProvider
{
	private ?string $cachedAccessToken = null;
	private ?int $cachedAccessTokenExpiresAt = null;

	abstract protected function requestAccessToken(): array;

	# --------------------
	# get the accesstoken for M365
	public function getAccessToken(): string
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

		$this->cachedAccessToken = $tokenResponse[ 'access_token' ];
		$this->cachedAccessTokenExpiresAt = time() + (int)( $tokenResponse[ 'expires_in' ] ?? 3599 );

		return( $this->cachedAccessToken );
	}

	# --------------------
	# Return an XOAUTH2 formatted auth string
	public function createXoauth2String( string $mailbox ): string
	{
		$accessToken = $this->getAccessToken();

		return( base64_encode(
			'user=' . $mailbox . "\x01" .
			'auth=Bearer ' . $accessToken . "\x01\x01"
		) );
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

		if ( $hasClientSecret === $hasCertificate )
		{
			throw new InvalidArgumentException( 'Configure either a client secret or a PFX certificate, but not both.' );
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
			throw new RuntimeException( 'clientSecret or pfxPath + pfxPassword is missing.' );
		}
	}

	# --------------------
	# Request accesstoken from M365
	protected function requestAccessToken(): array
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
			$postData[ 'client_assertion_type' ] = 'urn:ietf:params:oauth:client-assertion-type:jwt-bearer';
			$postData[ 'client_assertion' ] = $this->createClientAssertion();
		}
		else
		{
			throw new LogicException( 'No Microsoft authentication method is configured.' );	
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

			throw new RuntimeException(
				'Failed to request Microsoft 365 access token. ' . $errorDetails,
				(int)$e->getCode(),
				$e
			);
		}

		$body = (string)$response->getBody();
		$data = json_decode($body, true);

		if ( !is_array( $data ) )
		{
			throw new RuntimeException( 'Microsoft token response was not valid JSON: ' . $body );
		}

		if ( empty( $data[ 'access_token' ] ) )
		{
			throw new RuntimeException( 'Microsoft token response did not contain an access_token: ' . $body );
		}

		return( $data );
	}

	# --------------------
	# Create the ClientAssertion
	private function createClientAssertion(): string
	{
		$certificateData = $this->readPfx();

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
	private function readPfx(): array
	{
		if ( $this->certificateData !== null )
		{
			return( $this->certificateData );
		}

		if ( !is_file( $this->pfxPath ) )
		{
			throw new RuntimeException( 'PFX file not found: ' . $this->pfxPath );
		}

		$pfxContents = file_get_contents( $this->pfxPath );

		if ( $pfxContents === FALSE )
		{
			throw new RuntimeException( 'Could not read PFX file: ' . $this->pfxPath );
		}

		$certificates = [];

		if ( !openssl_pkcs12_read( $pfxContents, $certificates, $this->pfxPassword ) )
		{
			throw new RuntimeException( 'Could not read PFX file. Check the PFX password.' );
		}

		if ( empty( $certificates[ 'pkey' ] ) )
		{
			throw new RuntimeException( 'PFX file does not contain a private key.' );
		}

		if ( empty( $certificates[ 'cert' ] ) )
		{
			throw new RuntimeException( 'PFX file does not contain a certificate.' );
		}

		$certificateDer = self::pemCertificateToDer( $certificates[ 'cert' ] );

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
	private static function pemCertificateToDer( string $certificatePem ): string
	{
		$certificateDer = preg_replace(
			'/-----BEGIN CERTIFICATE-----|-----END CERTIFICATE-----|\s/',
			'',
			$certificatePem
		);

		if ( !is_string( $certificateDer ) || $certificateDer === '' )
		{
			throw new RuntimeException( 'Could not parse PEM certificate.' );
		}

		$decoded = base64_decode( $certificateDer, TRUE );

		if ( $decoded === FALSE )
		{
			throw new RuntimeException( 'Could not decode PEM certificate.' );
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
			throw new InvalidArgumentException( 'Google Workspace mailbox cannot be empty.' );
		}

		if (
			is_string( $this->serviceAccountCredentials ) &&
			!is_file( $this->serviceAccountCredentials )
		)
		{
			throw new InvalidArgumentException(
				'Google service-account JSON file was not found: ' .
				$this->serviceAccountCredentials
			);
		}
	}

	# --------------------
	# Request access token from Google
	protected function requestAccessToken(): array
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
			throw new RuntimeException(
				'Failed to request Google Workspace access token: ' .
					$e->getMessage(),
				(int)$e->getCode(),
				$e
			);
		}

		if ( !is_array( $tokenResponse ) )
		{
			throw new RuntimeException( 'Google token response was not an array.' );
		}

		return( $tokenResponse );
	}
}
?>
