<?php

declare(strict_types=1);

# Load Composer autoloader module tester
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) )
{
	require_once( __DIR__ . '/vendor/autoload.php' );
}

/**
 * EmailReporting transport adapter for the modern horde/imap_client API.
 * Composer: horde/imap_client ^3.1
 *
 * API: Horde\Imap\Client (PSR-4)
 * PHP: 8.2+
 */

plugin_require_api( 'core/error_api.php' );

abstract class ERP_Transport extends ERP_ErrorHandling
{
	protected bool $_test_only = FALSE;
	protected bool $_ssl_cert_verify = TRUE;

	protected ?object $_mailserver = NULL;

	protected array $_options = array();

	protected bool $_connected = FALSE;

	# --------------------
	# Get the applicable encryption method
	protected function getSecureMode( string|FALSE $p_encryption ): \Horde\Imap\Client\SecureMode
	{
		$t_secure = match( $p_encryption )
		{
			'STARTTLS' => \Horde\Imap\Client\SecureMode::StartTls,
			'SSL'      => \Horde\Imap\Client\SecureMode::Ssl,
			'TLS'      => \Horde\Imap\Client\SecureMode::Tls,
			'None'     => \Horde\Imap\Client\SecureMode::None,
			FALSE      => \Horde\Imap\Client\SecureMode::None,
			default    => \Horde\Imap\Client\SecureMode::None,
		};

		return( $t_secure );
	}

	# --------------------
	# Connect to a mailbox
	public function connect( string $p_hostname, int $p_port, string|FALSE $p_encryption = FALSE ): bool
	{
		$this->_options[ 'hostspec' ] = $p_hostname;
		$this->_options[ 'port' ] = $p_port;
		$this->_options[ 'secure' ] = $this->getSecureMode( $p_encryption );

		$this->_options[ 'saslPolicy' ] = \Horde\Sasl\Negotiation\SaslPolicy::secureDefaults(); // legacyCompatible OR secureDefaults

		return( TRUE );
	}

	# --------------------
	# Disconnect from a mailbox
	public function disconnect(): bool
	{
		if ( !$this->_connected || $this->_mailserver === NULL )
		{
			return( TRUE );
		}

		try
		{
			$this->_mailserver->logout();

			$this->_connected = FALSE;
		}
		catch ( \Throwable $t_exception )
		{
			$this->handleThrowable( $t_exception );
			return( FALSE );
		}

		$this->_mailserver = NULL;

		return( $this->_connected === FALSE );
	}
}

class ERP_POP3_Transport extends ERP_Transport
{
	# --------------------
	# Constructor
	public function __construct( bool $p_test_only = FALSE, int|bool $p_ssl_cert_verify = TRUE, int $p_timeout = 30 )
	{
		$this->_test_only = (bool) $p_test_only;
		$this->_ssl_cert_verify = (bool) $p_ssl_cert_verify;
		$this->_options[ 'timeout' ] = $p_timeout;

		$this->_options[ 'context' ] = array( 'ssl' => array(
			'verify_peer'      => $this->_ssl_cert_verify,
			'verify_peer_name' => $this->_ssl_cert_verify,
		) );

		if ( !class_exists( '\Horde\Imap\Client\Pop3Client' ) )
		{
			$this->setError( 'Horde/Imap_Client composer package missing.' );
			return;
		}
	}

	# --------------------
	# Get supported auth methods
	public function getsupportedAuthMethods(): array
	{
		$t_supportedAuthMethods = array( 'LOGIN', 'PLAIN', 'USER', 'XOAUTH2' );

		return( $t_supportedAuthMethods );
	}

	# --------------------
	# Perform the login to the mailbox
	public function login(
		string $p_mailbox_username,
		#[\SensitiveParameter]
		string $p_mailbox_password,
		string $p_mailbox_auth_method
	): bool
	{
		try
		{
			$t_configuration = new \Horde\Imap\Client\ConnectionConfig( ...$this->_options );

			if ( $p_mailbox_auth_method === 'XOAUTH2' )
			{
				$t_credential = new \Horde\Sasl\Credentials\TokenCredentials(
					$p_mailbox_username,
					new \Horde\Sasl\Credentials\PlainSecret( $p_mailbox_password )
				);
			}
			else
			{
				$t_credential = new \Horde\Sasl\Credentials\PasswordCredentials(
					$p_mailbox_username,
					new \Horde\Sasl\Credentials\PlainSecret( $p_mailbox_password )
				);
			}

			$this->_mailserver = new \Horde\Imap\Client\Pop3Client( $t_configuration, $t_credential );

			$this->_mailserver->login();

			$this->_connected = TRUE;
		}
		catch ( \Throwable $t_exception )
		{
			$t_additionalstring = ( ( $this->_ssl_cert_verify ) ? "\n" . 'This could possibly be because SSL certificate verification failed.' : '' );
			$t_additionalstring .= ' ' . ( ( $p_mailbox_auth_method === 'XOAUTH2' ) ? "\n" . 'This could possibly be a permission issue where the application has no permission to access the given mailbox.' : '' );
			$t_additionalstring = "\n" . trim( $t_additionalstring );
			$this->handleThrowable( $t_exception, $t_additionalstring );
			return( FALSE );
		}

		return( $this->_connected );
	}

	# --------------------
	# Return a list of emails in the mailbox
	public function getListing(): array|FALSE
	{
		if ( $this->_test_only )
		{
			return( array() );
		}

		try
		{
			// Need to do status to prevent a throw of 'No messages.'
			$t_status = $this->_mailserver->status(
				'INBOX',
				\Horde\Imap\Client\StatusFlag::Messages->value,
			);

			if ( $t_status->messages === 0 )
			{
				return( array() );
			}

			$t_query = new \Horde\Imap\Client\Pop3FetchQuery();
			$t_query->seq();

			$t_messages = $this->_mailserver->fetch(
				'INBOX',
				$this->_mailserver->getIdsOb(),
				$t_query
			);

			$t_ListMsgs = array();
			foreach ( $t_messages AS $t_uid => $t_message )
			{
				$t_ListMsgs[] = (int) $t_message->getSeq();
			}

			// Horde already sorts the list
			//sort( $t_ListMsgs, SORT_NUMERIC );
		}
		catch ( \Throwable $t_exception )
		{
			$this->handleThrowable( $t_exception );
			return( FALSE );
		}

		return( $t_ListMsgs );
	}

	# --------------------
	# Return a single raw email
	# @TODO Horde only returns the email one time for POP3. So a failure offers no retry.
	public function getMsg( int $p_msg_id ): string|FALSE
	{
		if ( $this->_test_only )
		{
			return( '' );
		}

		try
		{
			$t_query = new \Horde\Imap\Client\Pop3FetchQuery();
			$t_query->seq();
			$t_query->fullMsg();

			$t_messages = $this->_mailserver->fetch(
				'INBOX',
				$this->_mailserver->getIdsOb( $p_msg_id, TRUE ),
				$t_query
			);

			foreach ( $t_messages AS $t_uid => $t_message )
			{
				$t_raw_message = $t_message->getFullMsg()->getString();
			}

			if ( empty( $t_raw_message ) )
			{
				$this->setError( 'Message not found.' );
				return( FALSE );
			}
		}
		catch ( \Throwable $t_exception )
		{
			$this->handleThrowable( $t_exception );
			return( FALSE );
		}

		return( $t_raw_message );
	}

	# --------------------
	# Delete a single email from a mailbox
	public function deleteMsg( int $p_msg_id ): bool
	{
		if ( $this->_test_only )
		{
			return( TRUE );
		}

		try
		{
			$this->_mailserver->store(
				'INBOX',
				array(
					'ids' => $this->_mailserver->getIdsOb( $p_msg_id, TRUE ),
					'add' => array( \Horde\Imap\Client\SystemFlag::Deleted )
				)
			);
		}
		catch ( \Throwable $t_exception )
		{
			$this->handleThrowable( $t_exception );
			return( FALSE );
		}

		return( TRUE );
	}
}

class ERP_IMAP_Transport extends ERP_Transport
{
	private ?string $_hierarchydelimiter = NULL;

	# --------------------
	# Constructor
	public function __construct( bool $p_test_only = FALSE, int|bool $p_ssl_cert_verify = TRUE, int $p_timeout = 30 )
	{
		$this->_test_only = (bool) $p_test_only;
		$this->_ssl_cert_verify = (bool) $p_ssl_cert_verify;
		$this->_options[ 'timeout' ] = $p_timeout;

		$this->_options[ 'context' ] = array( 'ssl' => array(
			'verify_peer'      => $this->_ssl_cert_verify,
			'verify_peer_name' => $this->_ssl_cert_verify,
		) );

		if ( !class_exists( '\Horde\Imap\Client\ImapClient' ) )
		{
			$this->setError( 'Horde/Imap_Client composer package missing.' );
			return;
		}
	}

	# --------------------
	# Get supported auth methods
	public function getsupportedAuthMethods(): array
	{
		$t_supportedAuthMethods = array( 'LOGIN', 'PLAIN', 'XOAUTH2' );

		return( $t_supportedAuthMethods );
	}

	# --------------------
	# Perform the login to the mailbox
	public function login(
		string $p_mailbox_username,
		#[\SensitiveParameter]
		string $p_mailbox_password,
		string $p_mailbox_auth_method
	): bool
	{
		try
		{
			$t_configuration = new \Horde\Imap\Client\ConnectionConfig( ...$this->_options );

			if ( $p_mailbox_auth_method === 'XOAUTH2' )
			{
				$t_credential = new \Horde\Sasl\Credentials\TokenCredentials(
					$p_mailbox_username,
					new \Horde\Sasl\Credentials\PlainSecret( $p_mailbox_password )
				);
			}
			else
			{
				$t_credential = new \Horde\Sasl\Credentials\PasswordCredentials(
					$p_mailbox_username,
					new \Horde\Sasl\Credentials\PlainSecret( $p_mailbox_password )
				);
			}

			$this->_mailserver = new \Horde\Imap\Client\ImapClient( $t_configuration, $t_credential );

			$this->_mailserver->login();

			$this->selectMailbox( 'INBOX' );

			$this->_connected = TRUE;
		}
		catch ( \Throwable $t_exception )
		{
			$t_additionalstring = ( ( $this->_ssl_cert_verify ) ? "\n" . 'This could possibly be because SSL certificate verification failed.' : '' );
			$t_additionalstring .= ' ' . ( ( $p_mailbox_auth_method === 'XOAUTH2' ) ? "\n" . 'This could also be a permission issue where the application has no permission to access the given mailbox.' : '' );
			$t_additionalstring = "\n" . trim( $t_additionalstring );
			$this->handleThrowable( $t_exception, $t_additionalstring );
			return( FALSE );
		}

		return( $this->_connected );
	}

	# --------------------
	# Return a list of emails in the mailbox
	public function getListing(): array|FALSE
	{
		if ( $this->_test_only )
		{
			return( array() );
		}

		try
		{
			$t_result = $this->_mailserver->search(
				$this->getCurrentMailbox(),
				new \Horde\Imap\Client\ImapSearchQuery(),
				array()
			);

			$t_ListMsgs = $t_result->match->toArray();

			// Horde already sorts the list
			//sort( $t_ListMsgs, SORT_NUMERIC );
		}
		catch ( \Throwable $t_exception )
		{
			$this->handleThrowable( $t_exception );
			return( FALSE );
		}

		return( $t_ListMsgs );
	}

	# --------------------
	# Check whether a email is deleted
	# If FALSE is returned, check with hasError whether there was an error or if the state is FALSE (not marked as deleted)
	public function isDeleted( int $p_msg_id ): bool
	{
		if ( $this->_test_only )
		{
			return( FALSE );
		}

		try
		{
			$t_query = new \Horde\Imap\Client\ImapFetchQuery();
			$t_query->flags();

			$t_messages = $this->_mailserver->fetch(
				$this->getCurrentMailbox(),
				$this->_mailserver->getIdsOb( $p_msg_id ),
				$t_query
			);

			foreach ( $t_messages AS $t_uid => $t_message )
			{
				$t_flags = array_map( 'strtolower', $t_message->getFlags() );
				$t_isDeleted = in_array( strtolower( \Horde\Imap\Client\SystemFlag::Deleted->value ), $t_flags, TRUE );
			}

			if ( !isset( $t_isDeleted ) )
			{
				$this->setError( 'Message UID not found: ' . $p_msg_id );
				return( FALSE );
			}
		}
		catch ( \Throwable $t_exception )
		{
			$this->handleThrowable( $t_exception );
			return( FALSE );
		}

		return( $t_isDeleted );
	}

	# --------------------
	# Return a single raw email
	public function getMsg( int $p_msg_id ): string|FALSE
	{
		if ( $this->_test_only )
		{
			return( '' );
		}

		try
		{
			$t_query = new \Horde\Imap\Client\ImapFetchQuery();
			$t_query->fullMsg( NULL, NULL, FALSE );

			$t_messages = $this->_mailserver->fetch(
				$this->getCurrentMailbox(),
				$this->_mailserver->getIdsOb( $p_msg_id ),
				$t_query
			);

			foreach ( $t_messages AS $t_uid => $t_message )
			{
				$t_raw_message = $t_message->getFullMsg()->substring();
			}

			if ( empty( $t_raw_message ) )
			{
				$this->setError( 'Message not found.' );
				return( FALSE );
			}
		}
		catch ( \Throwable $t_exception )
		{
			$this->handleThrowable( $t_exception );
			return( FALSE );
		}

		return( $t_raw_message );
	}

	# --------------------
	# Delete a single email from a mailbox
	public function deleteMsg( int $p_msg_id ): bool
	{
		if ( $this->_test_only )
		{
			return( TRUE );
		}

		try
		{
			$this->_mailserver->store(
				$this->getCurrentMailbox(),
				array(
					'ids' => $this->_mailserver->getIdsOb( $p_msg_id ),
					'add' => array( \Horde\Imap\Client\SystemFlag::Deleted )
				)
			);
		}
		catch ( \Throwable $t_exception )
		{
			$this->handleThrowable( $t_exception );
			return( FALSE );
		}

		return( TRUE );
	}

	# --------------------
	# Get the hierarchy delimiter
	private function getHierarchyDelimiter(): string|FALSE
	{
		if ( $this->_hierarchydelimiter === NULL )
		{
			try
			{
				$t_mailboxes = $this->_mailserver->listMailboxes( '', \Horde\Imap\Client\MailboxListMode::All );

				$t_mailbox = reset( $t_mailboxes );

				$t_hierarchydelimiter = ( ( $t_mailbox ) ? (string) $t_mailbox[ 'delimiter' ] : FALSE );

				if ( $t_hierarchydelimiter === FALSE )
				{
					$this->setError( 'The mail server did not return a hierarchy delimiter.' );
					return( FALSE );
				}

				$this->_hierarchydelimiter = $t_hierarchydelimiter;
			}
			catch ( \Throwable $t_exception )
			{
				$this->handleThrowable( $t_exception );
				return( FALSE );
			}
		}

		return( $this->_hierarchydelimiter );
	}

	# --------------------
	# Prepare foldername
	# All p_foldernames should use / as folder separator
	private function prepareFoldername( string $p_foldername ): string|FALSE
	{
		$t_hierarchydelimiter = $this->getHierarchyDelimiter();

		if ( $t_hierarchydelimiter === FALSE )
		{
			return( FALSE );
		}

		$t_foldername = $p_foldername;
		if ( $t_hierarchydelimiter !== '/' )
		{
			$t_foldername = str_replace( '/', $t_hierarchydelimiter, $t_foldername );
		}

		// HordeModern handles this
		//$t_foldername = mb_convert_encoding( $t_foldername, "UTF7-IMAP" );

		return( $t_foldername );
	}

	# --------------------
	# Get the current folder for the mailbox
	public function getCurrentMailbox(): string|FALSE
	{
		try
		{
			$t_getCurrentMailbox = $this->_mailserver->selectedMailbox();

			// Need to catch the NULL value since we cannot use it. Happens with _test_only
			if ( $t_getCurrentMailbox === NULL )
			{
				if ( $this->_test_only )
				{
					return( 'INBOX' );
				}
				else
				{
					$this->setError( 'No current mailbox set.' );
					return( FALSE );
				}
			}
		}
		catch ( \Throwable $t_exception )
		{
			$this->handleThrowable( $t_exception );
			return( FALSE );
		}

		return( $t_getCurrentMailbox );
	}

	# --------------------
	# Check whether a folder exists.
	# If FALSE is returned, check with hasError whether there was an error or if the folder did not exist
	public function mailboxExist( string $p_foldername ): bool
	{
		$t_foldername = $this->prepareFoldername( $p_foldername );

		if ( $t_foldername === FALSE )
		{
			return( FALSE );
		}

		try
		{
			$t_mailboxExist = count( $this->_mailserver->listMailboxes( $t_foldername, \Horde\Imap\Client\MailboxListMode::All ) ) > 0;
		}
		catch ( \Throwable $t_exception )
		{
			$this->handleThrowable( $t_exception );
			return( FALSE );
		}

		return( $t_mailboxExist );
	}

	# --------------------
	# Select a mailbox folder
	public function selectMailbox( string $p_foldername ): bool
	{
		$t_foldername = $this->prepareFoldername( $p_foldername );

		if ( $t_foldername === FALSE )
		{
			return( FALSE );
		}

		try
		{
			$this->_mailserver->openMailbox( $t_foldername, ( ( $this->_test_only ) ? \Horde\Imap\Client\OpenMode::Readonly : \Horde\Imap\Client\OpenMode::ReadWrite ) );
		}
		catch ( \Throwable $t_exception )
		{
			$this->handleThrowable( $t_exception );
			return( FALSE );
		}

		return( TRUE );
	}

	# --------------------
	# Create the mailbox folder
	public function createMailbox( string $p_foldername ): bool
	{
		if ( $this->_test_only )
		{
			return( TRUE );
		}

		$t_foldername = $this->prepareFoldername( $p_foldername );

		if ( $t_foldername === FALSE )
		{
			return( FALSE );
		}

		try
		{
			$this->_mailserver->createMailbox( $t_foldername );
		}
		catch ( \Throwable $t_exception )
		{
			$this->handleThrowable( $t_exception );
			return( FALSE );
		}

		return( TRUE );
	}

	# --------------------
	# Expunge deleted email from current folder
	public function expunge(): bool
	{
		if ( $this->_test_only )
		{
			return( TRUE );
		}

		try
		{
			$this->_mailserver->expunge( $this->getCurrentMailbox() );
		}
		catch ( \Throwable $t_exception )
		{
			$this->handleThrowable( $t_exception );
			return( FALSE );
		}

		return( TRUE );
	}
}

?>
