<?php

abstract class ERP_PEAR_Transport
{
	protected $_ssl_cert_verify;

	protected $_mailserver = NULL;

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

		return( $t_loginresult );
	}

	public function deleteMsg( $p_msg_id )
	{
		$t_deleteresult = $this->_mailserver->deleteMsg( $p_msg_id );

		return( $t_deleteresult );
	}

}

class ERP_PEAR_POP3_Transport extends ERP_PEAR_Transport
{
	public function __construct( $p_ssl_cert_verify )
	{
		$this->_ssl_cert_verify = $p_ssl_cert_verify;

		$this->_mailserver = new Net_POP3();
		$this->_mailserver->_timeout = 3;
	}

	public function connect( $p_hostname, $p_port )
	{
		$t_connectresult = $this->_mailserver->connect( $p_hostname, $p_port, $this->get_StreamContextOptions() );

		return( $t_connectresult );
	}

	public function disconnect()
	{
		$this->_mailserver->disconnect();
	}

	# --------------------
	# Return a list of emails in the mailbox
	# Needed a workaround to sort IMAP emails in a certain order
	public function getListing()
	{
		$t_ListMsgs = $this->_mailserver->getListing();

		if ( !PEAR::isError( $t_ListMsgs ) )
		{
			$t_ListMsgs = array_column( $t_ListMsgs, NULL, 'msg_id' );

			krsort( $t_ListMsgs );
		}

		return( $t_ListMsgs );
	}

	# --------------------
	# Return a single raw email
	# Handles a workaround for problems with Net_IMAP 1.1.x concerning the getMsg function
	public function getMsg( $p_msg_id )
	{
		$t_msg = NULL;

		$t_msg = $this->_mailserver->getMsg( $p_msg_id );

		return( $t_msg );
	}
}

class ERP_PEAR_IMAP_Transport extends ERP_PEAR_Transport
{
	public $_connected = FALSE;

	public function __construct( $p_ssl_cert_verify )
	{
		$this->_ssl_cert_verify = $p_ssl_cert_verify;

		$this->_mailserver = new Net_IMAP( NULL );
		$this->_mailserver->setTimeout( 3 );

		$this->_mailserver->setStreamContextOptions( $this->get_StreamContextOptions() );

		$this->_connected = &$this->_mailserver->_connected;
	}

	public function connect( $p_hostname, $p_port, $p_STARTTLS = FALSE )
	{
		$p_STARTTLS = (
			$p_STARTTLS === TRUE ||
			$p_STARTTLS === 'STARTTLS'
		);

		$t_connectresult = $this->_mailserver->connect( $p_hostname, $p_port, $p_STARTTLS );

		return( $t_connectresult );
	}

	public function disconnect( $p_expunge )
	{
		$this->_mailserver->disconnect( (bool) $p_expunge );
	}

	# --------------------
	# Return a list of emails in the mailbox
	# Needed a workaround to sort IMAP emails in a certain order
	public function getListing()
	{
		$t_ListMsgs = $this->_mailserver->getListing();

		if ( !PEAR::isError( $t_ListMsgs ) )
		{
			$t_ListMsgs = array_column( $t_ListMsgs, NULL, 'uidl' );

			krsort( $t_ListMsgs );
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

		if ( is_array( $t_msg ) && count( $t_msg ) === 1 )
		{
			$t_msg = $t_msg[ key( $t_msg ) ];
		}

		return( $t_msg );
	}

	# --------------------
	# Check whether a email is deleted
	# for IMAP only function
	# Handles a workaround for problems with Net_IMAP 1.1.x with the hasFlag function (isDeleted uses that function)
	public function isDeleted( $p_msg_id, &$p_flags )
	{
//		return $this->hasFlag($message_nro, '\Deleted');
		$flag = '\Deleted';

		if ( $p_flags instanceOf PEAR_Error )
		{
			return $p_flags;
		}

		if ( isset( $p_flags[ $p_msg_id ] ) )
		{
			if ( is_array( $p_flags[ $p_msg_id ] ) )
			{
				if ( in_array( $flag, $p_flags[ $p_msg_id ] ) )
				{
					return TRUE;
				}
			}
		}

		return FALSE;
	}

	public function getCurrentMailbox()
	{
		$t_getCurrentMailbox = $this->_mailserver->getCurrentMailbox();

		return( $t_getCurrentMailbox );
	}

	public function mailboxExist( $p_foldername )
	{
		$t_mailboxExist = $this->_mailserver->mailboxExist( $p_foldername );

		return( $t_mailboxExist );
	}

	public function getHierarchyDelimiter()
	{
		$t_getHierarchyDelimiter = $this->_mailserver->getHierarchyDelimiter();

		return( $t_getHierarchyDelimiter );
	}

	public function examineMailbox( $p_foldername )
	{
		$t_examineMailbox = $this->_mailserver->examineMailbox( $p_foldername );

		return( $t_examineMailbox );
	}

	public function selectMailbox( $p_foldername )
	{
		$t_selectMailbox = $this->_mailserver->selectMailbox( $p_foldername );

		return( $t_selectMailbox );
	}

	public function getFlags()
	{
		$t_getFlags = $this->_mailserver->getFlags();

		return( $t_getFlags );
	}

	public function createMailbox( $p_foldername )
	{
		$t_createMailbox = $this->_mailserver->createMailbox( $p_foldername );

		return( $t_createMailbox );
	}
}
?>
