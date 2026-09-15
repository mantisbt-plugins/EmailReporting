<?php

declare(strict_types=1);

# Load Composer autoloader module tester
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) )
{
	require_once( __DIR__ . '/vendor/autoload.php' );
}

/**
 * EmailReporting adapter for webklex/php-imap 6.x.
 * Composer: webklex/php-imap
 */

plugin_require_api( 'core/error_api.php' );

abstract class ERP_Transport extends ERP_ErrorHandling
{
	protected bool $_test_only = FALSE;
	protected bool $_ssl_cert_verify = TRUE;

	protected ?object $_mailserver = NULL;

	protected array $_options = array();

	# --------------------
	# Check whether an operation result contains an error.
	#
	# When an error is detected it is stored internally.
	# Returns TRUE when an error was detected.
	protected function handleThrowable( mixed $p_result, string $t_additionalstring = '' ): bool
	{
		if ( $p_result Instanceof \Throwable )
		{
			$t_error = $p_result->getMessage() . ' (' . $p_result->getCode() . ').' . ( ( !empty( $t_additionalstring ) ) ? ' ' . $t_additionalstring : '' );

			$t_previous = $p_result->getPrevious();
			if ( $t_previous Instanceof \Throwable )
			{
				$t_error .= "\n" . $t_previous->getMessage() . ' (' . $t_previous->getCode() . ').';
			}

			$this->setError( $t_error );
			return( TRUE );
		}
		else
		{
			return( FALSE );
		}
	}
}

class ERP_POP3_Transport extends ERP_Transport
{
	# --------------------
	# Constructor
	public function __construct( bool $p_test_only = FALSE, int|bool $p_ssl_cert_verify = TRUE, int $p_timeout = 30 )
	{
		$this->setError( 'No support for POP3.' );
		return;

		if ( !class_exists( '\Webklex\PHPIMAP\ClientManager' ) )
		{
			$this->setError( 'Webklex/PHP-IMAP composer package missing.' );
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
	private ?object $_messages = NULL;

	private ?string $_hierarchydelimiter = NULL;

	# --------------------
	# Constructor
	public function __construct( bool $p_test_only = FALSE, int|bool $p_ssl_cert_verify = TRUE, int $p_timeout = 30 )
	{
		$this->_test_only = (bool) $p_test_only;
		$this->_ssl_cert_verify = (bool) $p_ssl_cert_verify;

		$this->_options[ 'validate_cert' ] = $this->_ssl_cert_verify;
		$this->_options[ 'protocol' ] = 'imap';
		$this->_options[ 'timeout' ] = $p_timeout;

		if ( !class_exists( '\Webklex\PHPIMAP\ClientManager' ) )
		{
			$this->setError( 'Webklex/PHP-IMAP composer package missing.' );
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
	# Connect to a mailbox
	public function connect( string $p_hostname, int $p_port, string|FALSE $p_encryption = FALSE ): bool
	{
		$this->_options[ 'host' ] = $p_hostname;
		$this->_options[ 'port' ] = $p_port;
		$this->_options[ 'encryption' ] = ( ( $p_encryption !== FALSE && $p_encryption !== 'None' ) ? strtolower( $p_encryption ) : FALSE );

		return( TRUE );
	}

	# --------------------
	# Disconnect from a mailbox
	public function disconnect( bool $p_expunge = FALSE ): bool
	{
		$this->_messages = NULL;

		if ( $this->_mailserver === NULL )
		{
			return( TRUE );
		}

		if ( !$this->_mailserver->isConnected() )
		{
			$this->_mailserver = NULL;
			return( TRUE );
		}

		try
		{
			if ( $p_expunge && !$this->_test_only )
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

		$this->_mailserver = NULL;

		return( TRUE );
	}

	# --------------------
	# Perform the login to the mailbox
	public function login( string $p_mailbox_username, string $p_mailbox_password, string $p_mailbox_auth_method ): bool
	{
		$this->_options[ 'username' ] = $p_mailbox_username;
		$this->_options[ 'password' ] = $p_mailbox_password;
		$this->_options[ 'authentication' ] = ( ( $p_mailbox_auth_method === 'XOAUTH2' ) ? 'oauth' : NULL );

		$this->_mailserver = NULL;
		$this->_messages = NULL;

		try
		{
			$t_cm = new \Webklex\PHPIMAP\ClientManager();
			$this->_mailserver = $t_cm->make( $this->_options );

			$this->_mailserver->getConfig()->set( 'options.fetch_body', FALSE );

			$this->runWithErrorAsException( 'connect', $this->_mailserver ); // $this->_mailserver->connect()

			$t_connectresult = $this->_mailserver->isConnected();
	
			if ( $t_connectresult )
			{
				$t_foldername = $this->_mailserver->getConfig()->get( 'options.common_folders.root' );

				$t_selectresult = $this->selectMailbox( $t_foldername );

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
	# Needed a workaround to sort IMAP emails in a certain order
	public function getListing(): array|FALSE
	{
		if ( $this->_test_only )
		{
			return( array() );
		}

		$this->_messages = NULL;

		$t_foldername = $this->getCurrentMailbox();

		if ( $t_foldername === FALSE )
		{
			return( FALSE );
		}

		try
		{
			$t_folder = $this->_mailserver->getFolderByPath( $t_foldername );

			$this->_messages = $t_folder->messages()->UNDELETED()->get();

			$t_ListMsgs = array();
			foreach ( $this->_messages->keys() AS $t_key )
			{
				$t_ListMsgs[] = (int) $t_key;
			}
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
	# Handles a workaround for problems with Net_IMAP 1.1.x with the hasFlag function (isDeleted uses that function)
	# If FALSE is returned, check with hasError whether there was an error or if the state is FALSE (not marked as deleted)
	public function isDeleted( int $p_msg_id ): bool
	{
		if ( $this->_test_only )
		{
			return( FALSE );
		}

		$t_flag = 'deleted';

		try
		{
			$t_isDeleted = $this->_messages[ $p_msg_id ]->hasFlag( $t_flag );
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
	# Handles a workaround for problems with Net_IMAP 1.1.x concerning the getMsg function
	public function getMsg( int $p_msg_id ): string|FALSE
	{
		if ( $this->_test_only )
		{
			return( '' );
		}

		try
		{
			$t_rawmessage = (string) $this->_messages[ $p_msg_id ]->getHeader()->raw . (string) $this->_messages[ $p_msg_id ]->getRawBody();
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
			$t_deleteresult = $this->_messages[ $p_msg_id ]->delete( FALSE );
		}
		catch ( \Throwable $t_exception )
		{
			$this->handleThrowable( $t_exception );
			return( FALSE );
		}

		return( $t_deleteresult );
	}

	# --------------------
	# Get the hierarchy delimiter
	#
	# Webklex uses the configured hierarchy delimiter.
	# Most IMAP servers use "/", but some servers may require ".".
	# Webklex does not perform a delimiter detection (last checked on 6.1.0)
	private function getHierarchyDelimiter(): string|FALSE
	{
		if ( $this->_hierarchydelimiter === NULL )
		{
			try
			{
				$t_hierarchydelimiter = $this->_mailserver->getConfig()->get( 'options.delimiter' );
			}
			catch ( \Throwable $t_exception )
			{
				$this->handleThrowable( $t_exception );
				return( FALSE );
			}

			$this->_hierarchydelimiter = $t_hierarchydelimiter;
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

		return( $t_foldername );
	}

	# --------------------
	# Get the current folder for the mailbox
	public function getCurrentMailbox(): string|FALSE
	{
		try
		{
			$t_getCurrentMailbox = $this->_mailserver->getActiveFolder();

			if ( $t_getCurrentMailbox === NULL )
			{
				$t_foldername = $this->_mailserver->getConfig()->get( 'options.common_folders.root' );

				$t_selectresult = $this->selectMailbox( $t_foldername );

				if ( $t_selectresult === FALSE )
				{
					return( FALSE );
				}

				$t_getCurrentMailbox = $this->_mailserver->getActiveFolder(); 

				// Need to catch the NULL value since we cannot return it. Happens with _test_only
				if ( $t_getCurrentMailbox === NULL && $this->_test_only )
				{
					$t_getCurrentMailbox = $t_foldername;
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
			$t_folders = $this->_mailserver->getFolders( FALSE );

			if ( count( $t_folders ) > 0 )
			{
				foreach ( $t_folders AS $t_folder )
				{
					if ( $t_folder->path === $t_foldername )
					{
						return( TRUE );
					}
				}
			}
		}
		catch ( \Throwable $t_exception )
		{
			$this->handleThrowable( $t_exception );
			return( FALSE );
		}

		return( FALSE );
	}

	# --------------------
	# Select a mailbox folder
	public function selectMailbox( string $p_foldername ): bool
	{
		if ( $this->_test_only )
		{
			return( TRUE );
		}

		$this->_messages = NULL;

		$t_foldername = $this->prepareFoldername( $p_foldername );

		if ( $t_foldername === FALSE )
		{
			return( FALSE );
		}

		try
		{
			$this->_mailserver->openFolder( $t_foldername );
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
			$this->_mailserver->createFolder( $t_foldername, FALSE );
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
