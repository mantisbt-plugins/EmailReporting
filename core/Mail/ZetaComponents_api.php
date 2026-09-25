<?php

declare(strict_types=1);

# Load Composer autoloader module tester
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) )
{
	require_once( __DIR__ . '/vendor/autoload.php' );
}

/**
 * EmailReporting adapters for zetacomponents/mail.
 * Composer: zetacomponents/mail
 */

plugin_require_api( 'core/error_api.php' );

abstract class ERP_Transport extends ERP_ErrorHandling
{
	protected bool $_test_only = FALSE;
	protected bool $_ssl_cert_verify = TRUE;

	protected ?object $_mailserver = NULL;

	protected ?object $_options = NULL;
	protected string $_hostname = '';
	protected int $_port = 0;

	# --------------------
	# Connect to a mailbox
	public function connect( string $p_hostname, int $p_port, string|FALSE $p_encryption = FALSE ): bool
	{
		$this->_hostname = $p_hostname;
		$this->_port = $p_port;
		$this->_options->ssl = (bool) ( $p_encryption !== FALSE && $p_encryption !== 'None' );

		return( TRUE );
	}

	# --------------------
	# Return a single raw email
	# @TODO Zeta only returns the email one time. So a failure offers no retry.
	public function getMsg( int $p_msg_id ): string|FALSE
	{
		if ( $this->_test_only )
		{
			return( '' );
		}

		try
		{
			$t_msg_set = $this->_mailserver->fetchByMessageNr( $p_msg_id );

			$t_parser = new ezcMailParser();

			$t_mail = $t_parser->parseMail( $t_msg_set );

			$t_rawmsg = $t_mail[ 0 ]->generate();
		}
		catch ( \Throwable $t_exception )
		{
			$this->handleThrowable( $t_exception );
			return( FALSE );
		}

		return( $t_rawmsg );
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
			$this->_mailserver->delete( $p_msg_id );
		}
		catch ( \Throwable $t_exception )
		{
			$this->handleThrowable( $t_exception );
			return( FALSE );
		}

		return( TRUE );
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

		if ( !class_exists( '\ezcMailPop3Transport' ) )
		{
			$this->setError( 'ZetaComponents/Mail composer package missing.' );
			return;
		}

		$this->_options = new \ezcMailPop3TransportOptions();
		$this->_options->timeout = $p_timeout;
	}

	# --------------------
	# Get supported auth methods
	public function getsupportedAuthMethods(): array
	{
		$t_supportedAuthMethods = array( 'APOP', 'PLAIN' );

		return( $t_supportedAuthMethods );
	}

	# --------------------
	# Disconnect from a mailbox
	public function disconnect(): bool
	{
		if ( $this->_mailserver === NULL )
		{
			return( TRUE );
		}

		try
		{
			$this->_mailserver->disconnect();
		}
		catch ( \Throwable $t_exception )
		{
			$this->handleThrowable( $t_exception );
			return( FALSE );
		}

		$this->_mailserver = NULL;

		return( TRUE );
	}

	# --------------------
	# Perform the login to the mailbox
	public function login( string $p_mailbox_username, string $p_mailbox_password, string $p_mailbox_auth_method ): bool
	{
		if ( $p_mailbox_auth_method === 'XOAUTH2' )
		{
			$this->setError( 'zetacomponents/mail does not provide XOAUTH2 authentication for POP3.' );
			return( FALSE );
		}

		try
		{
			$t_auth_method = match( $p_mailbox_auth_method )
			{
				'APOP'   => \ezcMailPop3Transport::AUTH_APOP,
				'PLAIN'  => \ezcMailPop3Transport::AUTH_PLAIN_TEXT,
				default  => $p_mailbox_auth_method,
			};

			$this->_mailserver = new \ezcMailPop3Transport( $this->_hostname, $this->_port, $this->_options );

			$this->_mailserver->authenticate( $p_mailbox_username, $p_mailbox_password, $t_auth_method );
		}
		catch ( \Throwable $t_exception )
		{
			$this->handleThrowable( $t_exception );
			return( FALSE );
		}

		return( TRUE );
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
			$t_messages = $this->_mailserver->listUniqueIdentifiers();

			$t_ListMsgs = array_keys( $t_messages );

			sort( $t_ListMsgs, SORT_NUMERIC );
		}
		catch ( \Throwable $t_exception )
		{
			$this->handleThrowable( $t_exception );
			return( FALSE );
		}

		return( $t_ListMsgs );
	}
}

class ERP_IMAP_Transport extends ERP_Transport
{
	// Zeta has a 'selectedMailbox' variable but it is protected and there is no way to retrieve it
	private string $_current_mailbox = '';

	private ?string $_hierarchydelimiter = NULL;

	# --------------------
	# Constructor
	public function __construct( bool $p_test_only = FALSE, int|bool $p_ssl_cert_verify = TRUE, int $p_timeout = 30 )
	{
		$this->_test_only = (bool) $p_test_only;
		$this->_ssl_cert_verify = (bool) $p_ssl_cert_verify;

		if ( !class_exists( '\ezcMailImapTransport' ) )
		{
			$this->setError( 'ZetaComponents/Mail composer package missing.' );
			return;
		}

		$this->_options = new \ezcMailImapTransportOptions();
		$this->_options->timeout = $p_timeout;
	}

	# --------------------
	# Get supported auth methods
	public function getsupportedAuthMethods(): array
	{
		$t_supportedAuthMethods = array();
		if ( class_exists( '\ezcMailImapTransport' ) )
		{
			$t_supportedAuthMethods = \ezcMailImapTransport::getSupportedAuthMethods();
		}

		return( $t_supportedAuthMethods );
	}

	# --------------------
	# Disconnect from a mailbox
	public function disconnect( bool $p_expunge = FALSE ): bool
	{
		if ( $this->_mailserver === NULL )
		{
			return( TRUE );
		}

		try
		{
			if ( $p_expunge && !$this->_test_only && !empty( $this->_current_mailbox ) )
			{
				$this->_mailserver->expunge();
			}

			$this->_mailserver->disconnect();
		}
		catch ( \Throwable $t_exception )
		{
			$this->handleThrowable( $t_exception );
			return( FALSE );
		}

		$this->_current_mailbox = '';
		$this->_hierarchydelimiter = NULL;
		$this->_mailserver = NULL;

		return( TRUE );
	}

	# --------------------
	# Perform the login to the mailbox
	public function login( string $p_mailbox_username, string $p_mailbox_password, string $p_mailbox_auth_method ): bool
	{
		try
		{
			$this->_mailserver = new \ezcMailImapTransport( $this->_hostname, $this->_port, $this->_options );

			$this->_mailserver->authenticate( $p_mailbox_username, $p_mailbox_password, $p_mailbox_auth_method );

			$t_selectresult = $this->selectMailbox( 'INBOX' );

			if ( $t_selectresult === FALSE )
			{
				return( FALSE );
			}
		}
		catch ( \Throwable $t_exception )
		{
			$this->handleThrowable( $t_exception );
			return( FALSE );
		}

		return( TRUE );
	}

	# --------------------
	# Return a list of emails in the mailbox
	# Zeta returns only the messages with the flag DELETED not set
	public function getListing(): array|FALSE
	{
		if ( $this->_test_only )
		{
			return( array() );
		}

		try
		{
			$t_messages = $this->_mailserver->listUniqueIdentifiers();

			$t_ListMsgs = array();
			foreach ( $t_messages AS $t_number => $t_uid )
			{
				$t_ListMsgs[ (int) $t_uid ] = (int) $t_number;
			}

			ksort( $t_ListMsgs, SORT_NUMERIC );
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
	# Zeta returns only the messages with the flag DELETED not set (see getListing)
	public function isDeleted( int $p_msg_id ): bool
	{
		if ( $this->_test_only )
		{
			return( FALSE );
		}

		try
		{
			$t_flags = $this->_mailserver->fetchFlags( array( $p_msg_id ) );

			$t_isdeleted = in_array( '\Deleted', $t_flags[ $p_msg_id ] ?? array(), TRUE );
		}
		catch ( \Throwable $t_exception )
		{
			$this->handleThrowable( $t_exception );
			return( FALSE );
		}

		return( $t_isdeleted );
	}

	# --------------------
	# Get the hierarchy delimiter
	private function getHierarchyDelimiter(): string|FALSE
	{
		if ( $this->_hierarchydelimiter === NULL )
		{
			try
			{
				$this->_hierarchydelimiter = (string) $this->_mailserver->getHierarchyDelimiter();
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

		$t_foldername = mb_convert_encoding( $t_foldername, "UTF7-IMAP" );

		return( $t_foldername );
	}

	# --------------------
	# Get the current folder for the mailbox
	public function getCurrentMailbox(): string|FALSE
	{
		$t_current_mailbox = ( ( $this->_current_mailbox !== '' ) ? $this->_current_mailbox : FALSE );

		if ( $t_current_mailbox === FALSE )
		{
			return( FALSE );
		}

		return( $t_current_mailbox );
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
			$t_mailboxes = $this->_mailserver->listMailboxes( '', $t_foldername );

			$t_mailboxExist = in_array( $t_foldername, $t_mailboxes, TRUE );
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
			$this->_mailserver->selectMailbox( $t_foldername, $this->_test_only );

			$this->_current_mailbox = $t_foldername;
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
}

?>
