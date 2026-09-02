<?php

declare(strict_types=1);

/**
 * Error handling contract:
 *
 **- Methods return their normal success/failure values.
 * - Transport errors are stored internally.
 * - callers must check hasError()/getError() after a failed operation.
 * * getError() clears the stored error.
 */

plugin_require_api( 'core/error_api.php' );

abstract class ERP_Transport extends ERP_ErrorHandling
{
	protected bool $_test_only = FALSE;
	protected bool $_ssl_cert_verify = TRUE;

	protected ?object $_mailserver = NULL;

	# Return Stream Context Options array
	protected function get_StreamContextOptions(): array
	{
		return( array(
			'ssl' => array
			(
				'verify_peer'      => $this->_ssl_cert_verify,
				'verify_peer_name' => $this->_ssl_cert_verify
			)
		) );
	}

	# --------------------
	# return the hostname with an encryption prefix (if applicable)
	protected function prepare_mailbox_hostname( string $p_hostname, string|false $p_encryption = FALSE ): string|FALSE
	{
		$t_hostname = $p_hostname;

		if ( $p_encryption === FALSE || $p_encryption === 'None' || $p_encryption === 'STARTTLS' )
		{
			return( $t_hostname );
		}

		if ( !extension_loaded( 'openssl' ) )
		{
			$this->setError( 'OpenSSL plugin not available even though the mailbox is configured to use it. Please check whether OpenSSL is properly being loaded.' );
			return( FALSE );
		}

		$t_socket_transports = stream_get_transports();

		if ( !in_array( strtolower( $p_encryption ), $t_socket_transports, TRUE ) )
		{
			$this->setError( 'Unknown encryption selected: ' . $p_encryption );
			return( FALSE );
		}

		// The IMAP pear package will enable encryption after the connection is established if the default port is used. So we need to work around that
		// No longer needed since we disabled the code in question in IMAPProtocol.php
		//if ( !( $this->_mailbox[ 'mailbox_type' ] === 'IMAP' && ( $this->_mailbox[ 'port' ] <= 0 || $this->_mailbox[ 'port' ] === $this->_default_ports[ $this->_mailbox[ 'mailbox_type' ] ][ $t_def_mailbox_port_index ] ) ) )
		{
			$t_hostname = strtolower( $p_encryption ) . '://' . $t_hostname;
		}

		return( $t_hostname );
	}

	# --------------------
	# Perform the login to the mailbox
	public function login( string $p_mailbox_username, string $p_mailbox_password, string $p_mailbox_auth_method ): bool
	{
		$t_loginresult = $this->_mailserver->login( $p_mailbox_username, $p_mailbox_password, $p_mailbox_auth_method );

		$t_additionalstring = ( ( $p_mailbox_auth_method === 'XOAUTH2' ) ? ' This could also be a permission issue where the application has no permission to access the given mailbox.' : '' );
		if ( $this->isError( $t_loginresult, $t_additionalstring ) )
		{
			return( FALSE );
		}

		return( $t_loginresult );
	}

	# --------------------
	# Delete a single email from a mailbox
	public function deleteMsg( int $p_msg_id ): bool
	{
		if ( $this->_test_only === TRUE )
		{
			return( TRUE );
		}

		$t_deleteresult = $this->_mailserver->deleteMsg( $p_msg_id );

		if ( $this->isError( $t_deleteresult ) )
		{
			return( FALSE );
		}

		return( $t_deleteresult );
	}

	# --------------------
	# Get supported auth methods
	public function getsupportedAuthMethods(): array
	{
		$t_supportedAuthMethods = $this->_mailserver->supportedAuthMethods;

		return( $t_supportedAuthMethods );
	}

	# --------------------
	# Check whether an operation result contains an error.
	#
	# When an error is detected it is stored internally.
	# Returns TRUE when an error was detected.
	#
	# Passed by reference to minimise memory usage when
	# handling large result objects and mailbox data.
	protected function isError( mixed &$p_result, string $t_additionalstring = '' ): bool
	{
		if ( PEAR::isError( $p_result ) )
		{
			$this->setError( $p_result->getMessage() . ' (' . $p_result->getCode() . ').' . $t_additionalstring );

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
	public function __construct( bool $p_test_only = FALSE, int|bool $p_ssl_cert_verify = TRUE )
	{
		$this->_test_only = (bool) $p_test_only;
		$this->_ssl_cert_verify = (bool) $p_ssl_cert_verify;

		$this->_mailserver = new Net_POP3();
		$this->_mailserver->_timeout = 3;
	}

	# --------------------
	# Connnect to a mailbox
	public function connect( string $p_hostname, int $p_port, string|FALSE $p_encryption = FALSE ): bool
	{
		$t_hostname = $this->prepare_mailbox_hostname( $p_hostname, $p_encryption );

		if ( $t_hostname === FALSE )
		{
			return( FALSE );
		}

		$t_connectresult = $this->_mailserver->connect( $t_hostname, $p_port, $this->get_StreamContextOptions() );

		$t_additionalstring = ( ( $p_encryption !== FALSE && $p_encryption !== 'None' && $this->_ssl_cert_verify === TRUE ) ? ' This could possibly be because SSL certificate verification failed.' : '' );
		if ( $this->isError( $t_connectresult, $t_additionalstring ) )
		{
			return( FALSE );
		}

		if ( $t_connectresult === FALSE )
		{
			$this->setError( 'Failed to connect to the mail server.' . $t_additionalstring );
		}

		return( $t_connectresult );
	}

	# --------------------
	# Disconnect from a mailbox
	public function disconnect(): bool
	{
		if ( $this->_mailserver->_state === NET_POP3_STATE_DISCONNECTED )
		{
			return( TRUE );
		}

		$t_disconnectresult = $this->_mailserver->disconnect();

		if ( $this->isError( $t_disconnectresult ) )
		{
			return( FALSE );
		}

		return( $t_disconnectresult );
	}

	# --------------------
	# Return a list of emails in the mailbox
	public function getListing(): array|FALSE
	{
		if ( $this->_test_only === TRUE )
		{
			return( array() );
		}

		$t_ListMsgs = $this->_mailserver->getListing();

		if ( $this->isError( $t_ListMsgs ) )
		{
			return( FALSE );
		}

		$t_ListMsgs = array_column( $t_ListMsgs, NULL, 'msg_id' );

		ksort( $t_ListMsgs );

		return( $t_ListMsgs );
	}

	# --------------------
	# Return a single raw email
	public function getMsg( int $p_msg_id ): string|FALSE
	{
		if ( $this->_test_only === TRUE )
		{
			return( '' );
		}

		$t_msg = $this->_mailserver->getMsg( $p_msg_id );

		if ( $this->isError( $t_msg ) )
		{
			return( FALSE );
		}

		return( $t_msg );
	}
}

class ERP_IMAP_Transport extends ERP_Transport
{
	private bool $_connected = FALSE;

	private array $_getFlags = array();

	# --------------------
	# Constructor
	public function __construct( bool $p_test_only = FALSE, int|bool $p_ssl_cert_verify = TRUE )
	{
		$this->_test_only = (bool) $p_test_only;
		$this->_ssl_cert_verify = (bool) $p_ssl_cert_verify;

		$this->_mailserver = new Net_IMAP( NULL );
		$this->_mailserver->setTimeout( 3 );

		$this->_mailserver->setStreamContextOptions( $this->get_StreamContextOptions() );

		$this->_connected = &$this->_mailserver->_connected;
	}

	# --------------------
	# Connect to a mailbox
	public function connect( string $p_hostname, int $p_port, string|FALSE $p_encryption = FALSE ): bool
	{
		$t_hostname = $this->prepare_mailbox_hostname( $p_hostname, $p_encryption );

		if ( $t_hostname === FALSE )
		{
			return( FALSE );
		}

		$t_STARTTLS = $p_encryption === 'STARTTLS';

		$t_connectresult = $this->_mailserver->connect( $t_hostname, $p_port, $t_STARTTLS );

		$t_additionalstring = ( ( $p_encryption !== FALSE && $p_encryption !== 'None' && $this->_ssl_cert_verify === TRUE ) ? ' This could possibly be because SSL certificate verification failed.' : '' );
		if ( $this->isError( $t_connectresult, $t_additionalstring ) )
		{
			return( FALSE );
		}

		if ( $this->_connected !== $t_connectresult )
		{
			$this->setError( 'IMAP state discrepency: _connected and connectresult show different states.' );
			return( FALSE );
		}

		if ( $t_connectresult === FALSE )
		{
			$this->setError( 'Failed to connect to the mail server.' . $t_additionalstring );
		}

		return( $t_connectresult );
	}

	# --------------------
	# Disconnect from a mailbox
	public function disconnect( bool $p_expunge = FALSE ): bool
	{
		if ( $this->_connected !== TRUE )
		{
			return( TRUE );
		}

		//$this->_mailserver->->expunge(); //disabled as this is handled by the disconnect
		$t_disconnectresult = $this->_mailserver->disconnect( (bool) $p_expunge );

		if ( $this->isError( $t_disconnectresult ) )
		{
			return( FALSE );
		}

		return( $t_disconnectresult );
	}

	# --------------------
	# Return a list of emails in the mailbox
	# Needed a workaround to sort IMAP emails in a certain order
	public function getListing(): array|FALSE
	{
		if ( $this->_test_only === TRUE )
		{
			return( array() );
		}

		// Exchange does not seem to like numMsg so that was changed to getListing
		// getListing returns an error when there are no emails in an IMAP folder.
		// After 10 errors Exchange will ignore the connection and any further commands will fail with ", "
		// examineMailbox allows EmailReporting to check whether or not there are emails in the folder without producing an error

		$t_foldername = $this->getCurrentMailbox();
		if ( $t_foldername === FALSE )
		{
			return( FALSE );
		}

		$t_examineMailbox = $this->_mailserver->examineMailbox( $t_foldername );

		if ( $this->isError( $t_examineMailbox ) )
		{
			return( FALSE );
		}

		if ( $t_examineMailbox[ 'EXISTS' ] == 0 )
		{
			return( array() );
		}

		$t_ListMsgs = $this->_mailserver->getListing();

		if ( $this->isError( $t_ListMsgs ) )
		{
			return( FALSE );
		}

		if ( !empty( $t_ListMsgs ) )
		{
			$t_ListMsgs = array_column( $t_ListMsgs, NULL, 'uidl' );

			ksort( $t_ListMsgs );
		}

		return( $t_ListMsgs );
	}

	# --------------------
	# Return a single raw email
	# Handles a workaround for problems with Net_IMAP 1.1.x concerning the getMsg function
	public function getMsg( int $p_msg_id ): string|FALSE
	{
		if ( $this->_test_only === TRUE )
		{
			return( '' );
		}

		// Net_IMAP 1.1.0 and 1.1.2 seems to have a somewhat broken getMsg function.
		$t_msg = $this->_mailserver->getMessages( $p_msg_id, TRUE );

		if ( $this->isError( $t_msg ) )
		{
			return( FALSE );
		}

		if ( is_array( $t_msg ) && count( $t_msg ) === 1 )
		{
			$t_msg = $t_msg[ key( $t_msg ) ];
		}

		return( $t_msg );
	}

	# --------------------
	# Check whether a email is deleted
	# Handles a workaround for problems with Net_IMAP 1.1.x with the hasFlag function (isDeleted uses that function)
	# If FALSE is returned, check with hasError whether there was an error or if the state is FALSE (not marked as deleted)
	public function isDeleted( int $p_msg_id ): bool
	{
		if ( $this->_test_only === TRUE )
		{
			return( FALSE );
		}

		//return $this->hasFlag($message_nro, '\Deleted');
		$flag = '\Deleted';

		// Cache Flags results
		if ( empty( $this->_getFlags ) )
		{
			$this->_getFlags = $this->_mailserver->getFlags();

			if ( $this->isError( $this->_getFlags ) )
			{
				return( FALSE );
			}
		}
		$t_getFlags = $this->_getFlags;

		if ( isset( $t_getFlags[ $p_msg_id ] ) )
		{
			if ( is_array( $t_getFlags[ $p_msg_id ] ) )
			{
				if ( in_array( $flag, $t_getFlags[ $p_msg_id ] ) )
				{
					return( TRUE );
				}
			}
		}

		return( FALSE );
	}

	# --------------------
	# Get the current folder for the mailbox
	public function getCurrentMailbox(): string|FALSE
	{
		$t_getCurrentMailbox = $this->_mailserver->getCurrentMailbox();

		if ( $this->isError( $t_getCurrentMailbox ) )
		{
			return( FALSE );
		}

		return( $t_getCurrentMailbox );
	}

	# --------------------
	# Check whether a folder exists.
	# If FALSE is returned, check with hasError whether there was an error or if the folder did not exist
	public function mailboxExist( string $p_foldername ): bool
	{
		$t_mailboxExist = $this->_mailserver->mailboxExist( $p_foldername );

		if ( $this->isError( $t_mailboxExist ) )
		{
			return( FALSE );
		}

		return( $t_mailboxExist );
	}

	# --------------------
	# Get the hierarchy delimiter
	public function getHierarchyDelimiter(): string|FALSE
	{
		$t_getHierarchyDelimiter = $this->_mailserver->getHierarchyDelimiter();

		if ( $this->isError( $t_getHierarchyDelimiter ) )
		{
			return( FALSE );
		}

		return( $t_getHierarchyDelimiter );
	}

	# --------------------
	# Select a mailbox folder
	public function selectMailbox( string $p_foldername ): bool
	{
		if ( $this->_test_only === TRUE )
		{
			return( TRUE );
		}

		$t_selectMailbox = $this->_mailserver->selectMailbox( $p_foldername );

		if ( $this->isError( $t_selectMailbox ) )
		{
			return( FALSE );
		}

		// reset Flags cache
		$this->_getFlags = array();

		return( $t_selectMailbox );
	}

	# --------------------
	# Create the mailbox folder
	public function createMailbox( string $p_foldername ): bool
	{
		if ( $this->_test_only === TRUE )
		{
			return( TRUE );
		}

		$t_createMailbox = $this->_mailserver->createMailbox( $p_foldername );

		if ( $this->isError( $t_createMailbox ) )
		{
			return( FALSE );
		}

		return( $t_createMailbox );
	}
}
?>
