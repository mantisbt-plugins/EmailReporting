<?php

/**
 * Error handling contract:
 *
 **- Methods return their normal success/failure values.
 * - Transport errors are stored internally.
 * - callers must check hasError()/getError() after a failed operation.
 * * getError() clears the stored error.
 */

abstract class ERP_PEAR_Transport
{
	protected $_ssl_cert_verify;

	protected $_mailserver = NULL;

	private array $_error = array();

	# Return Stream Context Options array
	protected function get_StreamContextOptions()
	{
		return( array(
			'ssl' => array
			(
				'verify_peer'      => (bool) $this->_ssl_cert_verify,
				'verify_peer_name' => (bool) $this->_ssl_cert_verify
			)
		) );
	}

	# --------------------
	# Perform the login to the mailbox
	public function login( $p_mailbox_username, $p_mailbox_password, $p_mailbox_auth_method )
	{
		$t_loginresult = $this->_mailserver->login( $p_mailbox_username, $p_mailbox_password, $p_mailbox_auth_method );

		if ( $this->pear_error( $t_loginresult ) )
		{
			return( FALSE );
		}

		return( $t_loginresult );
	}

	# --------------------
	# Delete a single email from a mailbox
	public function deleteMsg( $p_msg_id )
	{
		$t_deleteresult = $this->_mailserver->deleteMsg( $p_msg_id );

		if ( $this->pear_error( $t_deleteresult ) )
		{
			return( FALSE );
		}

		return( $t_deleteresult );
	}

	# --------------------
	# Get supported auth methods
	public function getsupportedAuthMethods()
	{
		$t_supportedAuthMethods = $this->_mailserver->supportedAuthMethods;

		return( $t_supportedAuthMethods );
	}

	# --------------------
	# Set pear error when pear operation failed
	#  return a boolean for whether the mailbox has failed
	protected function pear_error( &$p_pear )
	{
		if ( PEAR::isError( $p_pear ) )
		{
			$this->setError( $p_pear->getMessage() . '(' . $p_pear->getCode() . ')' );

			return( TRUE );
		}
		else
		{
			return( FALSE );
		}
	}

	# --------------------
	# Check whether there are stored errors
	public function hasError(): bool
	{
		return( !empty( $this->_error ) );
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

class ERP_PEAR_POP3_Transport extends ERP_PEAR_Transport
{
	# --------------------
	# Constructor
	public function __construct( $p_ssl_cert_verify = TRUE )
	{
		$this->_ssl_cert_verify = $p_ssl_cert_verify;

		$this->_mailserver = new Net_POP3();
		$this->_mailserver->_timeout = 3;
	}

	# --------------------
	# Connnect to a mailbox
	public function connect( $p_hostname, $p_port )
	{
		$t_connectresult = $this->_mailserver->connect( $p_hostname, $p_port, $this->get_StreamContextOptions() );

		if ( $this->pear_error( $t_connectresult ) )
		{
			return( FALSE );
		}

		return( $t_connectresult );
	}

	# --------------------
	# Disconnect from a mailbox
	public function disconnect()
	{
		if ( $this->_mailserver->_state === NET_POP3_STATE_DISCONNECTED )
		{
			return( TRUE );
		}

		$t_disconnectresult = $this->_mailserver->disconnect();

		if ( $this->pear_error( $t_disconnectresult ) )
		{
			return( FALSE );
		}

		return( $t_disconnectresult );
	}

	# --------------------
	# Return a list of emails in the mailbox
	public function getListing()
	{
		$t_ListMsgs = $this->_mailserver->getListing();

		if ( $this->pear_error( $t_ListMsgs ) )
		{
			return( FALSE );
		}

		$t_ListMsgs = array_column( $t_ListMsgs, NULL, 'msg_id' );

		krsort( $t_ListMsgs );

		return( $t_ListMsgs );
	}

	# --------------------
	# Return a single raw email
	public function getMsg( $p_msg_id )
	{
		$t_msg = NULL;

		$t_msg = $this->_mailserver->getMsg( $p_msg_id );

		if ( $this->pear_error( $t_msg ) )
		{
			return( FALSE );
		}

		return( $t_msg );
	}
}

class ERP_PEAR_IMAP_Transport extends ERP_PEAR_Transport
{
	private $_connected = FALSE;

	private $_getFlags = array();

	# --------------------
	# Constructor
	public function __construct( $p_ssl_cert_verify = TRUE )
	{
		$this->_ssl_cert_verify = $p_ssl_cert_verify;

		$this->_mailserver = new Net_IMAP( NULL );
		$this->_mailserver->setTimeout( 3 );

		$this->_mailserver->setStreamContextOptions( $this->get_StreamContextOptions() );

		$this->_connected = &$this->_mailserver->_connected;
	}

	# --------------------
	# Connect to a mailbox
	public function connect( $p_hostname, $p_port, $p_STARTTLS = FALSE )
	{
		$p_STARTTLS = (
			$p_STARTTLS === TRUE ||
			$p_STARTTLS === 'STARTTLS'
		);

		$t_connectresult = $this->_mailserver->connect( $p_hostname, $p_port, $p_STARTTLS );

		if ( $this->pear_error( $t_connectresult ) )
		{
			return( FALSE );
		}

		if ( $this->_connected !== $t_connectresult )
		{
			$this->setError( 'IMAP state discrepency: _connected and connectresult show different states.' );
			return( FALSE );
		}

		return( $t_connectresult );
	}

	# --------------------
	# Disconnect from a mailbox
	public function disconnect( $p_expunge = FALSE )
	{
		if ( $this->_connected !== TRUE )
		{
			return( TRUE );
		}

		$t_disconnectresult = $this->_mailserver->disconnect( (bool) $p_expunge );

		if ( $this->pear_error( $t_disconnectresult ) )
		{
			return( FALSE );
		}

		return( $t_disconnectresult );
	}

	# --------------------
	# Return a list of emails in the mailbox
	# Needed a workaround to sort IMAP emails in a certain order
	public function getListing()
	{
		// Exchange does not seem to like numMsg so that was changed to getListing
		// getListing returns an error when there are no emails in an IMAP folder.
		// After 10 errors Exchange will ignore the connection and any further commands will fail with ", "
		// examineMailbox allows EmailReporting to check whether or not there are emails in the folder without producing an error

		$t_foldername = $this->getCurrentMailbox();
		if ( $t_foldername === FALSE )
		{
			return( FALSE );
		}

		$t_examineresult = $this->examineMailbox( $t_foldername );

		if ( $t_examineresult === FALSE )
		{
			return( FALSE );
		}

		if ( $t_examineresult[ 'EXISTS' ] == 0 )
		{
			return( array() );
		}

		$t_ListMsgs = $this->_mailserver->getListing();

		if ( $this->pear_error( $t_ListMsgs ) )
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
	public function getMsg( $p_msg_id )
	{
		$t_msg = NULL;

		// Net_IMAP 1.1.0 and 1.1.2 seems to have a somewhat broken getMsg function.
		$t_msg = $this->_mailserver->getMessages( $p_msg_id, TRUE );

		if ( $this->pear_error( $t_msg ) )
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
	public function isDeleted( $p_msg_id )
	{
//		return $this->hasFlag($message_nro, '\Deleted');
		$flag = '\Deleted';

		// Cache Flags results
		if ( empty( $this->_getFlags ) )
		{
			$this->_getFlags = $this->_mailserver->getFlags();

			if ( $this->pear_error( $this->_getFlags ) )
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
	public function getCurrentMailbox()
	{
		$t_getCurrentMailbox = $this->_mailserver->getCurrentMailbox();

		if ( $this->pear_error( $t_getCurrentMailbox ) )
		{
			return( FALSE );
		}

		return( $t_getCurrentMailbox );
	}

	# --------------------
	# Check whether a folder exists.
	# If FALSE is returned, check with hasError whether there was an error or if the folder did not exist
	public function mailboxExist( $p_foldername )
	{
		$t_mailboxExist = $this->_mailserver->mailboxExist( $p_foldername );

		if ( $this->pear_error( $t_mailboxExist ) )
		{
			return( FALSE );
		}

		return( $t_mailboxExist );
	}

	# --------------------
	# Get the hierarchy delimiter
	public function getHierarchyDelimiter()
	{
		$t_getHierarchyDelimiter = $this->_mailserver->getHierarchyDelimiter();

		if ( $this->pear_error( $t_getHierarchyDelimiter ) )
		{
			return( FALSE );
		}

		return( $t_getHierarchyDelimiter );
	}

	# --------------------
	# Examine the mailbox folder
	private function examineMailbox( $p_foldername )
	{
		$t_examineMailbox = $this->_mailserver->examineMailbox( $p_foldername );

		if ( $this->pear_error( $t_examineMailbox ) )
		{
			return( FALSE );
		}

		return( $t_examineMailbox );
	}

	# --------------------
	# Select a mailbox folder
	public function selectMailbox( $p_foldername )
	{
		$t_selectMailbox = $this->_mailserver->selectMailbox( $p_foldername );

		if ( $this->pear_error( $t_selectMailbox ) )
		{
			return( FALSE );
		}

		// reset Flags cache
		$this->_getFlags = array();

		return( $t_selectMailbox );
	}

	# --------------------
	# Create the mailbox folder
	public function createMailbox( $p_foldername )
	{
		$t_createMailbox = $this->_mailserver->createMailbox( $p_foldername );

		if ( $this->pear_error( $t_createMailbox ) )
		{
			return( FALSE );
		}

		return( $t_createMailbox );
	}
}
?>
