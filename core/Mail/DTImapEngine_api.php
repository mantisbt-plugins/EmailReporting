<?php

declare(strict_types=1);

# Load Composer autoloader module tester
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) )
{
	require_once( __DIR__ . '/vendor/autoload.php' );
}

/**
 * EmailReporting transport adapter for directorytree/imapengine.
 * Composer: directorytree/imapengine
 */

plugin_require_api( 'core/error_api.php' );

abstract class ERP_Transport extends ERP_ErrorHandling
{
	protected bool $_test_only = FALSE;
	protected bool $_ssl_cert_verify = TRUE;

	protected ?object $_mailserver = NULL;
}

class ERP_POP3_Transport extends ERP_Transport
{
	# --------------------
	# Constructor
	public function __construct( bool $p_test_only = FALSE, int|bool $p_ssl_cert_verify = TRUE, int $p_timeout = 30 )
	{
		$this->setError( 'No support for POP3.' );
		return;

		if ( !class_exists( '\DirectoryTree\ImapEngine\Mailbox' ) )
		{
			$this->setError( 'DirectoryTree/ImapEngine composer package missing.' );
			return;
		}
	}

	# --------------------
	# Get supported auth methods
	public function getsupportedAuthMethods(): array
	{
		$t_supportedAuthMethods = array(); // no support POP3: 'USER',

		return( $t_supportedAuthMethods );
	}

	# --------------------
	# Connnect to a mailbox
	public function connect( string $p_hostname, int $p_port, string|FALSE $p_encryption = FALSE ): bool
	{
		$this->__construct();

		return( FALSE );
	}
}

class ERP_IMAP_Transport extends ERP_Transport
{
	private ?object $_current_mailbox = NULL;

	private array $_options = array();

	private ?object $_messages = NULL;

	private ?string $_hierarchydelimiter = NULL;

	# --------------------
	# Constructor
	public function __construct( bool $p_test_only = FALSE, int|bool $p_ssl_cert_verify = TRUE, int $p_timeout = 30 )
	{
		$this->_test_only = (bool) $p_test_only;
		$this->_ssl_cert_verify = (bool) $p_ssl_cert_verify;

		$this->_options[ 'validate_cert' ] = $this->_ssl_cert_verify;
		$this->_options[ 'timeout' ] = $p_timeout;

		if ( !class_exists( '\DirectoryTree\ImapEngine\Mailbox' ) )
		{
			$this->setError( 'DirectoryTree/ImapEngine composer package missing.' );
			return;
		}
	}

	# --------------------
	# Get supported auth methods
	public function getsupportedAuthMethods(): array
	{
		$t_supportedAuthMethods = array( 'LOGIN', 'PLAIN',  'XOAUTH2' );

		return( $t_supportedAuthMethods );
	}

	# --------------------
	# Connect to a mailbox
	public function connect( string $p_hostname, int $p_port, string|FALSE $p_encryption = FALSE ): bool
	{
		$this->_options[ 'host' ] = $p_hostname;
		$this->_options[ 'port' ] = $p_port;
		$this->_options[ 'encryption' ] = ( ( $p_encryption !== FALSE && $p_encryption !== 'None' ) ? $p_encryption : 'tcp' );

		return( TRUE );
	}

	# --------------------
	# Disconnect from a mailbox
	public function disconnect(): bool
	{
		$this->_current_mailbox = NULL;
		$this->_messages = NULL;
		$this->_hierarchydelimiter = NULL;

		if ( $this->_mailserver === NULL )
		{
			return( TRUE );
		}

		if ( !$this->_mailserver->connected() )
		{
			$this->_mailserver = NULL;
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
	public function login( string $p_mailbox_username, string $p_mailbox_password, string $p_mailbox_auth_method): bool
	{
		$this->_options[ 'username' ] = $p_mailbox_username;
		$this->_options[ 'password' ] = $p_mailbox_password;
		if ( $p_mailbox_auth_method === 'XOAUTH2' )
		{
			$this->_options[ 'authentication' ] = 'oauth';
		}

		$this->_mailserver = NULL;
		$this->_messages = NULL;

		try
		{
			$this->_mailserver = new \DirectoryTree\ImapEngine\Mailbox( $this->_options );

			$this->_mailserver->connect();

			$t_connectresult = $this->_mailserver->connected();

			if ( $t_connectresult )
			{
				$t_selectresult = $this->selectMailbox( 'INBOX' );

				if ( $t_selectresult === FALSE )
				{
					return( FALSE );
				}
			}
		}
		catch ( \Throwable $t_exception )
		{
			$t_additionalstring = ( ( $this->_ssl_cert_verify ) ? 'This could possibly be because SSL certificate verification failed.' : '' );
			$t_additionalstring .= ' ' . ( ( $p_mailbox_auth_method === 'XOAUTH2' ) ? 'This could also be a permission issue where the application has no permission to access the given mailbox.' : '' );
			$t_additionalstring = trim( $t_additionalstring );
			$this->handleThrowable( $t_exception, $t_additionalstring );
			return( FALSE );
		}

		return( $t_connectresult );
	}

	# --------------------
	# Return a list of emails in the mailbox
	public function getListing(): array|FALSE
	{
		if ( $this->_test_only )
		{
			return( array() );
		}

		$this->_messages = NULL;

		if ( $this->getCurrentMailbox() === FALSE )
		{
			return( FALSE );
		}

		try
		{
			$this->_messages = $this->_current_mailbox->messages()->withFlags()->withBody()->withHeaders()->oldest()->UNDELETED()->get();

			$t_ListMsgs = array();
			foreach ( $this->_messages->keys() AS $t_key )
			{
				$t_ListMsgs[] = (int) $t_key;
			}

			// No sort or UID's as keys. DT ImapEngine should already provide a sorted list.
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
	# DT ImapEngine returns only the messages with the flag DELETED not set (see getListing)
	public function isDeleted( int $p_msg_id ): bool
	{
		if ( $this->_test_only )
		{
			return( FALSE );
		}

		try
		{
			$t_isDeleted = $this->_messages[ $p_msg_id ]->isDeleted();
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
			// Possible future memory optimization
			//var_dump($this->_current_mailbox->mailbox()->connection()->bodyHeader($uid));
			//var_dump($this->_current_mailbox->mailbox()->connection()->bodyText($uid));
			$t_rawmessage = $this->_messages[ $p_msg_id ]->__toString();
		}
		catch ( \Throwable $t_exception )
		{
			$this->handleThrowable( $t_exception );
			return( FALSE );
		}

		return( $t_rawmessage );
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
			$this->_messages[ $p_msg_id ]->delete( FALSE );
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
				$t_folder = $this->_mailserver->folders()->find( 'INBOX' );

				$this->_hierarchydelimiter = (string) $t_folder->delimiter();
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

		// DT ImapEngine handles this
		//$t_foldername = mb_convert_encoding( $t_foldername, "UTF7-IMAP" );

		return( $t_foldername );
	}

	# --------------------
	# Get the current folder for the mailbox
	# @TODO Unable to access selectedMailbox as its protected. Found no other way to access it yet
	public function getCurrentMailbox(): string|FALSE
	{
		// Need to catch the NULL value since we cannot use it. Happens with _test_only
		if ( $this->_current_mailbox === NULL )
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

		try
		{
			$t_getCurrentMailbox = $this->_mailserver->selected( $this->_current_mailbox );

			if ( $t_getCurrentMailbox === FALSE )
			{
				$this->setError( 'Selected mailbox out-of-sync.' );
				return( FALSE );
			}
		}
		catch ( \Throwable $t_exception )
		{
			$this->handleThrowable( $t_exception );
			return( FALSE );
		}

		return( $this->_current_mailbox->path() );
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
			$t_folder = $this->_mailserver->folders()->find( $t_foldername );
		}
		catch ( \Throwable $t_exception )
		{
			$this->handleThrowable( $t_exception );
			return( FALSE );
		}

		if ( $t_folder === NULL )
		{
			return( FALSE );
		}

		return( $t_folder !== FALSE );
	}

	# --------------------
	# Select a mailbox folder
	public function selectMailbox( string $p_foldername ): bool
	{
		$this->_current_mailbox = NULL;
		$this->_messages = NULL;

		$t_foldername = $this->prepareFoldername( $p_foldername );

		if ( $t_foldername === FALSE )
		{
			return( FALSE );
		}

		try
		{
			$t_folder = $this->_mailserver->folders()->find( $t_foldername );

			if ( $t_folder === NULL )
			{
				$this->setError( 'Folder not found: "' . $t_foldername . '"' );
				return( FALSE );
			}

			$this->_mailserver->select( $t_folder );

			$this->_current_mailbox = $t_folder;
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
			$t_folder = $this->_mailserver->folders()->create( $t_foldername );
		}
		catch ( \Throwable $t_exception )
		{
			$this->handleThrowable( $t_exception );
			return( FALSE );
		}

		if ( $t_folder === NULL )
		{
			$this->setError( 'Create folder failed: "' . $t_foldername . '"' );
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

		if ( $this->getCurrentMailbox() === FALSE )
		{
			return( FALSE );
		}

		try
		{
			$this->_current_mailbox->expunge();
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
