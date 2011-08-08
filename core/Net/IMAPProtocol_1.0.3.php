<?php
//
// +----------------------------------------------------------------------+
// | PHP Version 4                                                        |
// +----------------------------------------------------------------------+
// | Copyright (c) 1997-2003 The PHP Group                                |
// +----------------------------------------------------------------------+
// | This source file is subject to version 2.02 of the PHP license,      |
// | that is bundled with this package in the file LICENSE, and is        |
// | available at through the world-wide-web at                           |
// | http://www.php.net/license/2_02.txt.                                 |
// | If you did not receive a copy of the PHP license and are unable to   |
// | obtain it through the world-wide-web, please send a note to          |
// | license@php.net so we can mail you a copy immediately.               |
// +----------------------------------------------------------------------+
// | Author: Damian Alejandro Fernandez Sosa <damlists@cnba.uba.ar>       |
// +----------------------------------------------------------------------+
require_once 'Net/Socket.php';



/**
 * Provides an implementation of the IMAP protocol using PEAR's
 * Net_Socket:: class.
 *
 * @package Net_IMAP/Protocol
 * @author  Damian Alejandro Fernandez Sosa <damlists@cnba.uba.ar>
 */
class Net_IMAPProtocol {


    /**
    * The auth methods this class support
    * @var array
    */
    var $supportedAuthMethods=array('DIGEST-MD5', 'CRAM-MD5', 'LOGIN');


    /**
    * The auth methods this class support
    * @var array
    */
    var $supportedSASLAuthMethods=array('DIGEST-MD5', 'CRAM-MD5');

     /**
     * _serverAuthMethods
     * @var boolean
     */
    var $_serverAuthMethods = null;


    /**
     * The the current mailbox
     * @var string
     */
    var $currentMailbox = "INBOX" ;


    /**
     * The socket resource being used to connect to the IMAP server.
     * @var resource
     */
    var $_socket = null;

     /**
     * To allow class debuging
     * @var boolean
     */
    var $_debug = false;

    var $dbgDialog = '';

     /**
     * Command Number
     * @var int
     */
    var $_cmd_counter = 1;


     /**
     * Command Number for IMAP commands
     * @var int
     */
    var $_lastCmdID = 1;

     /**
     * Command Number
     * @var boolean
     */
    var $_unParsedReturn = false;



     /**
     * _connected: checks if there is a connection made to a imap server or not
     * @var boolean
     */
    var $_connected = false;
     /**
     * Capabilities
     * @var boolean
     */
    var $_serverSupportedCapabilities = null;



     /**
     * Use UTF-7 funcionallity
     * @var boolean
     */
    //var $_useUTF_7 = false;
    var $_useUTF_7 = true;



    /**
     * Constructor
     *
     * Instantiates a new Net_IMAP object.
     *
     * @since  1.0
     */
    function Net_IMAPProtocol()
    {
        $this->_socket = new Net_Socket();

        /*
         * Include the Auth_SASL package.  If the package is not available,
         * we disable the authentication methods that depend upon it.
         */


        if ((@include_once 'Auth/SASL.php') == false) {
            foreach($this->supportedSASLAuthMethods as $SASLMethod){
                $pos = array_search( $SASLMethod , $this->supportedAuthMethods);
                unset($this->supportedAuthMethods[$pos]);
            }
        }

    }


    /**
     * Attempt to connect to the IMAP server.
     *
     * @return mixed Returns a PEAR_Error with an error message on any
     *               kind of failure, or true on success.
     * @access public
     * @since  1.0
     */
    function cmdConnect($host= "localhost"  , $port = 143)
    {
        if( $this->_connected ){
            return new PEAR_Error( 'already connected, logout first!' );
        }
        if ( PEAR::isError( $this->_socket->connect( $host , $port ) ) ) {
            return new PEAR_Error( 'unable to open socket' );
        }
        if ( PEAR::isError( $this->_getRawResponse() ) ) {
            return new PEAR_Error( 'unable to open socket' );
        }
        $this->_connected = true;
        return true;
    }


    /**
     * get the cmd ID
     *
     * @return string Returns the CmdID and increment the counter
     *
     * @access private
     * @since  1.0
     */
    function _getCmdId()
    {
        $this->_lastCmdID = "A000" . $this->_cmd_counter ;
        $this->_cmd_counter++;
        return $this->_lastCmdID;
    }


    /**
     * get the last cmd ID
     *
     * @return string Returns the last cmdId
     *
     * @access public
     * @since  1.0
     */
    function getLastCmdId()
    {
        return $this->_lastCmdID;
    }




    /**
     * get current mailbox name
     *
     * @return string Returns the current mailbox
     *
     * @access public
     * @since  1.0
     */
    function getCurrentMailbox()
    {
        return $this->currentMailbox;
    }




    /**
     * Sets the debuging information on or off
     *
     * @param boolean True or false
     *
     * @return nothing
     * @access public
     * @since  1.0
     */
    function setDebug($debug = true)
    {
        $this->_debug = $debug;
    }


    function getDebugDialog()
    {
        return $this->dbgDialog;
    }



    /**
     * Send the given string of data to the server.
     *
     * @param   string  $data    The string of data to send.
     *
     * @return  mixed   True on success or a PEAR_Error object on failure.
     *
     * @access  private
     * @since  1.0
     */
    function _send($data)
    {
        if($this->_socket->eof() ){
            return new PEAR_Error( 'Failed to write to socket: (connection lost!) ' );
        }
        if ( PEAR::isError( $error = $this->_socket->write( $data ) ) ) {

            return new PEAR_Error( 'Failed to write to socket: ' .
                                  $error->getMessage() );
        }

        if( $this->_debug ){
            // C: means this data was sent by  the client (this class)
            echo "C: $data";
            $this->dbgDialog.="C: $data";
        }
        return true;
    }

    /**
     * Receive the given string of data from the server.
     *
     * @return  mixed   a line of response on success or a PEAR_Error object on failure.
     *
     * @access  private
     * @since  1.0
     */
    function _recvLn()
    {

        if (PEAR::isError( $this->lastline = $this->_socket->gets( 8192 ) ) ) {
            return new PEAR_Error('Failed to write to socket: ' .
                                              $this->lastline->getMessage() );
        }
        if($this->_debug){
            // S: means this data was sent by  the IMAP Server
            echo "S: " . $this->lastline . "" ;
            $this->dbgDialog.="S: " . $this->lastline . "" ;
        }
        if( $this->lastline == '' ){
            return new PEAR_Error('Failed to receive from the  socket: '  );
        }
        return $this->lastline;
    }





    /**
     * Send a command to the server with an optional string of arguments.
     * A carriage return / linefeed (CRLF) sequence will be appended to each
     * command string before it is sent to the IMAP server.
     *
     * @param   string  $commandId  The IMAP cmdID to send to the server.
     * @param   string  $command    The IMAP command to send to the server.
     * @param   string  $args       A string of optional arguments to append
     *                              to the command.
     *
     * @return  mixed   The result of the _send() call.
     *
     * @access  private
     * @since  1.0
     */
    function _putCMD($commandId , $command, $args = '')
    {
        if ( !empty( $args ) ) {
            return $this->_send( $commandId . " " . $command . ' ' . $args . "\r\n" );
        }
        return $this->_send( $commandId . " " . $command . "\r\n" );
    }






    /**
     * Get a response from the server with an optional string of commandID.
     * A carriage return / linefeed (CRLF) sequence will be appended to each
     * command string before it is sent to the IMAP server.
     *
     * @param   string  $commandid    The IMAP commandid retrive from the server.
     *
     * @return  string   The result response.
     *
     * @access  private
     */
    function _getRawResponse($commandId = '*')
    {
       $arguments = '';
       while ( !PEAR::isError( $this->_recvLn() ) ) {
           $reply_code = strtok( $this->lastline , ' ' );
           $arguments.= $this->lastline;
           if ( !(strcmp( $commandId , $reply_code ) ) ) {
           return $arguments;
           }
       }
       return $arguments;
     }





     /**
     * get the "returning of the unparsed response" feature status
     *
     * @return boolean return if the unparsed response is returned or not
     *
     * @access public
     * @since  1.0
     *
     */
    function getUnparsedResponse()
    {
        return $this->_unParsedReturn;
    }






     /**
     * set the "returning of the unparsed response" feature on or off
     *
     * @param  boolean  $status: true: feature is on
     * @return nothing
     *
     * @access public
     * @since  1.0
     */
    function setUnparsedResponse($status)
    {
        $this->_unParsedReturn = $status;
    }






    /**
     * Attempt to login to the iMAP server.
     *
     * @param string The userid to authenticate as.
     * @param string The password to authenticate with.
     *
     * @return array Returns an array containing the response
     *
     * @access public
     * @since  1.0
     */
    function cmdLogin($uid , $pwd)
    {
        $param="\"$uid\" \"$pwd\"";
        return $this->_genericCommand('LOGIN', $param);
    }






    /**
     * Attempt to authenticate to the iMAP server.
     * @param string The userid to authenticate as.
     * @param string The password to authenticate with.
     * @param string The cmdID.
     *
     * @return array Returns an array containing the response
     *
     * @access public
     * @since  1.0
     */
    function cmdAuthenticate($uid , $pwd , $userMethod = null)
    {

        if( !$this->_connected ){
            return new PEAR_Error('not connected!');
        }

        $cmdid = $this->_getCmdId();


        if ( PEAR::isError( $method = $this->_getBestAuthMethod($userMethod) ) ) {
            return $method;
        }


        switch ($method) {
            case 'DIGEST-MD5':
                $result = $this->_authDigest_MD5( $uid , $pwd , $cmdid );
                break;
            case 'CRAM-MD5':
                $result = $this->_authCRAM_MD5( $uid , $pwd ,$cmdid );
                break;
            case 'LOGIN':
                $result = $this->_authLOGIN( $uid , $pwd , $cmdid );
                break;

            default :
                $result = new PEAR_Error( "$method is not a supported authentication method" );
                break;
        }

        $args = $this->_getRawResponse( $cmdid );
        return $this->_genericImapResponseParser( $args , $cmdid );

    }








     /* Authenticates the user using the DIGEST-MD5 method.
     *
     * @param string The userid to authenticate as.
     * @param string The password to authenticate with.
     * @param string The cmdID.
     *
     * @return array Returns an array containing the response
     *
     * @access private
     * @since  1.0
     */
    function _authDigest_MD5($uid , $pwd , $cmdid)
    {

        if ( PEAR::isError($error = $this->_putCMD( $cmdid ,"AUTHENTICATE" , "DIGEST-MD5") ) ) {
            return $error;
        }

        if (PEAR::isError( $args = $this->_recvLn() ) ) {
            return $args;
        }

        $this->_getNextToken( $args , $plus );

        $this->_getNextToken( $args , $space );

        $this->_getNextToken( $args , $challenge );

        $challenge = base64_decode( $challenge );

        $digest = &Auth_SASL::factory('digestmd5');

        $auth_str = base64_encode($digest->getResponse($uid, $pwd, $challenge,"localhost", "imap"));

        if ( PEAR::isError( $error = $this->_send("$auth_str\r\n"))) {
            return $error;
        }

        if ( PEAR::isError( $args = $this->_recvLn() )) {
            return $args;
        }
        /*
         * We don't use the protocol's third step because IMAP doesn't allow
         * subsequent authentication, so we just silently ignore it.
         */
        if ( PEAR::isError( $error = $this->_send( "\r\n" ) ) ) {
            return $error;
        }
    }








     /* Authenticates the user using the CRAM-MD5 method.
     *
     * @param string The userid to authenticate as.
     * @param string The password to authenticate with.
     * @param string The cmdID.
     *
     * @return array Returns an array containing the response
     *
     * @access private
     * @since  1.0
     */
    function _authCRAM_MD5($uid, $pwd, $cmdid)
    {



        if ( PEAR::isError($error = $this->_putCMD( $cmdid ,"AUTHENTICATE" , "CRAM-MD5") ) ) {
            return $error;
        }

        if ( PEAR::isError( $args = $this->_recvLn() ) ) {
            return $args;
        }

        $this->_getNextToken( $args , $plus );

        $this->_getNextToken( $args , $space );

        $this->_getNextToken( $args , $challenge );

        $challenge = base64_decode( $challenge );

        $cram = &Auth_SASL::factory('crammd5');

        $auth_str = base64_encode( $cram->getResponse( $uid , $pwd , $challenge ) );

        if ( PEAR::isError( $error = $this->_send( $auth_str."\r\n" ) ) ) {
            return $error;
        }

    }









     /* Authenticates the user using the LOGIN method.
     *
     * @param string The userid to authenticate as.
     * @param string The password to authenticate with.
     * @param string The cmdID.
     *
     * @return array Returns an array containing the response
     *
     * @access private
     * @since  1.0
     */
    function _authLOGIN($uid, $pwd, $cmdid)
    {

        if (PEAR::isError($error = $this->_putCMD($cmdid,"AUTHENTICATE", "LOGIN"))) {
            return $error;
        }

        if (PEAR::isError($args = $this->_recvLn() )) {
            return $args;
        }

        $this->_getNextToken( $args , $plus );

        $this->_getNextToken( $args , $space );

        $this->_getNextToken( $args , $challenge );

        $challenge = base64_decode( $challenge );

        $auth_str = base64_encode( "$uid" );

        if ( PEAR::isError( $error = $this->_send( $auth_str."\r\n" ) ) ) {
            return $error;
        }

        if (PEAR::isError( $args = $this->_recvLn() ) ) {
            return $args;
        }

        $auth_str = base64_encode( "$pwd" );

        if ( PEAR::isError($error = $this->_send( $auth_str."\r\n" ) ) ) {
            return $error;
        }

    }








    /**
     * Returns the name of the best authentication method that the server
     * has advertised.
     *
     * @param string if !=null,authenticate with this method ($userMethod).
     *
     * @return mixed    Returns a string containing the name of the best
     *                  supported authentication method or a PEAR_Error object
     *                  if a failure condition is encountered.
     * @access private
     * @since  1.0
     */
    function _getBestAuthMethod($userMethod = null)
    {
       $this->cmdCapability();

        if($userMethod != null ){

            $methods = array();

            $methods[] = $userMethod;

        }else{
            $methods = $this->supportedAuthMethods;
        }

        if( ($methods != null) && ($this->_serverAuthMethods != null)){
            foreach ( $methods as $method ) {
                if ( in_array( $method , $this->_serverAuthMethods ) ) {
                    return $method;
                }
            }
            $serverMethods=implode(',' ,$this->_serverAuthMethods);
            $myMethods=implode(',' ,$this->supportedAuthMethods);
            return new PEAR_Error("$method NOT supported authentication method!. This IMAP server " .
                "supports these methods: $serverMethods, but I support $myMethods");
        }else{
            return new PEAR_Error("This IMAP server don't support any Auth methods");
        }
    }









    /**
     * Attempt to disconnect from the iMAP server.
     *
     * @return array Returns an array containing the response
     *
     * @access public
     * @since  1.0
     * 
     * EmailReporting: Fixed problem with the return value so that logout will work again. Based on the cmdLogout function in Net_IMAP 1.1.0
     */
    function cmdLogout()
    {
        if( !$this->_connected ){
            return new PEAR_Error( 'not connected!' );
        }

        if (PEAR::isError($args = $this->_genericCommand('LOGOUT'))) {
            return $args;
        }
        if (PEAR::isError($this->_socket->disconnect())) {
            return new PEAR_Error('socket disconnect failed');
        }

        return $args;

        // not for now
        //return $this->_genericImapResponseParser($args,$cmdid);

    }





    /**
     * Send the NOOP command.
     *
     * @return array Returns an array containing the response
     *
     * @access public
     * @since  1.0
     */
    function cmdNoop()
    {
        return $this->_genericCommand('NOOP');
    }









    /**
     * Send the CHECK command.
     *
     * @return array Returns an array containing the response
     *
     * @access public
     * @since  1.0
     */
    function cmdCheck()
    {
        return $this->_genericCommand('CHECK');
    }










    /**
     * Send the  Select Mailbox Command
     *
     * @param string The mailbox to select.
     *
     * @return array Returns an array containing the response
     *
     * @access public
     * @since  1.0
     */
    function cmdSelect($mailbox)
    {
        $mailbox_name=sprintf("\"%s\"",$this->utf_7_encode($mailbox) );
        if( !PEAR::isError( $ret= $this->_genericCommand('SELECT', $mailbox_name) ) ){
            $this->currentMailbox  = $mailbox;
        }
        return $ret;
    }










    /**
     * Send the  EXAMINE  Mailbox Command
     *
     * @param string The mailbox to examine.
     * @return array Returns an array containing the response
     *
     * @access public
     * @since  1.0
     */
    function cmdExamine($mailbox)
    {

        $mailbox_name=sprintf("\"%s\"",$this->utf_7_encode($mailbox) );
        $ret=$this->_genericCommand('EXAMINE', $mailbox_name);
        $parsed='';
        if(isset( $ret["PARSED"] ) ){
            for($i=0;$i<count($ret["PARSED"]); $i++){ $command=$ret["PARSED"][$i]["EXT"];
                    $parsed[key($command)]=$command[key($command)];
            }
        }
        return array("PARSED"=>$parsed,"RESPONSE"=>$ret["RESPONSE"]);
    }







    /**
     * Send the  CREATE Mailbox Command
     *
     * @param string The mailbox to create.
     * @return array Returns an array containing the response
     *
     * @access public
     * @since  1.0
     */
    function cmdCreate($mailbox)
    {
        $mailbox_name=sprintf("\"%s\"",$this->utf_7_encode($mailbox) );
        return $this->_genericCommand('CREATE', $mailbox_name);
    }







    /**
     * Send the  RENAME Mailbox Command
     *
     * @param string The old mailbox name.
     * @param string The new (renamed) mailbox name.
     *
     * @return array Returns an array containing the response
     *
     * @access public
     * @since  1.0
     */
    function cmdRename($mailbox, $new_mailbox)
    {
        $mailbox_name=sprintf("\"%s\"",$this->utf_7_encode($mailbox) );
        $new_mailbox_name=sprintf("\"%s\"",$this->utf_7_encode($new_mailbox) );
        return $this->_genericCommand('RENAME', "$mailbox_name $new_mailbox_name" );
    }








    /**
     * Send the  DELETE Mailbox Command
     *
     * @param string The mailbox name to delete.
     *
     * @return array Returns an array containing the response
     *
     * @access public
     * @since  1.0
     */
    function cmdDelete($mailbox)
    {
        $mailbox_name=sprintf("\"%s\"",$this->utf_7_encode($mailbox) );
        return $this->_genericCommand('DELETE', $mailbox_name);
    }







    /**
     * Send the  SUSCRIBE  Mailbox Command
     *
     * @param string The mailbox name to suscribe.
     *
     * @return array Returns an array containing the response
     *
     * @access public
     * @since  1.0
     */
    function cmdSubscribe($mailbox)
    {
        $mailbox_name=sprintf("\"%s\"",$this->utf_7_encode($mailbox) );
        return $this->_genericCommand('SUBSCRIBE', $mailbox_name );
    }








    /**
     * Send the  UNSUSCRIBE  Mailbox Command
     *
     * @return mixed Returns a PEAR_Error with an error message on any
     *               kind of failure, or true on success.
     * @access public
     * @since  1.0
     */
    function cmdUnsubscribe($mailbox)
    {
        $mailbox_name=sprintf("\"%s\"",$this->utf_7_encode($mailbox) );
        return $this->_genericCommand('UNSUBSCRIBE', $mailbox_name );
    }








    /**
     * Send the  FETCH Command
     *
     * @return mixed Returns a PEAR_Error with an error message on any
     *               kind of failure, or true on success.
     * @access public
     * @since  1.0
     */
    function cmdFetch($msgset, $fetchparam)
    {
        return $this->_genericCommand('FETCH' , "$msgset $fetchparam" );
    }







    /**
     * Send the  CAPABILITY Command
     *
     * @return mixed Returns a PEAR_Error with an error message on any
     *               kind of failure, or true on success.
     * @access public
     * @since  1.0
     */
    function cmdCapability()
    {
        $ret = $this->_genericCommand( 'CAPABILITY' );

        if(isset( $ret["PARSED"] ) ){
            $ret["PARSED"]=$ret["PARSED"][0]["EXT"]["CAPABILITY"];
            //fill the $this->_serverAuthMethods and $this->_serverSupportedCapabilities arrays
            foreach( $ret["PARSED"]["CAPABILITIES"] as $auth_method ){
                if( strtoupper( substr( $auth_method , 0 ,5 ) ) == "AUTH=" )
                    $this->_serverAuthMethods[] = substr( $auth_method , 5 );
            }
            // Keep the capabilities response to use ir later
            $this->_serverSupportedCapabilities = $ret["PARSED"]["CAPABILITIES"];
        }

        return $ret;
    }










    /**
     * Send the  STATUS Mailbox Command
     *
     * @param string $mailbox the mailbox name
     * @param string $request the request status it could be:
     *              MESSAGES | RECENT | UIDNEXT
     *              UIDVALIDITY | UNSEEN
     * @return array Returns a Parsed Response
     *
     * @access public
     * @since  1.0
     */
    function cmdStatus($mailbox, $request)
    {

        $mailbox_name=sprintf("\"%s\"",$this->utf_7_encode($mailbox) );

        if( $request!="MESSAGES" && $request!="RECENT" && $request!="UIDNEXT" &&
            $request!="UIDVALIDITY" && $request!="UNSEEN" ){
            // TODO:  fix this error!
            $this->_prot_error("request '$request' is invalid! see RFC2060!!!!" , __LINE__ , __FILE__, false );
        }
        $ret = $this->_genericCommand('STATUS', "$mailbox_name ($request)" );
        if(isset( $ret["PARSED"] ) ){
            $ret['PARSED']=$ret["PARSED"][count($ret['PARSED'])-1]["EXT"];
        }
        return $ret;
    }






    /**
     * Send the  LIST  Command
     *
     * @return mixed Returns a PEAR_Error with an error message on any
     *               kind of failure, or true on success.
     * @access public
     * @since  1.0
     */
    function cmdList($mailbox_base, $mailbox)
    {
        $mailbox_name=sprintf("\"%s\"",$this->utf_7_encode($mailbox) );
        $mailbox_base=sprintf("\"%s\"",$this->utf_7_encode($mailbox_base) );
        return $this->_genericCommand('LIST', "$mailbox_base $mailbox_name" );
    }






    /**
     * Send the  LSUB  Command
     *
     * @return mixed Returns a PEAR_Error with an error message on any
     *               kind of failure, or true on success.
     * @access public
     * @since  1.0
     */
    function cmdLsub($mailbox_base, $mailbox)
    {
        $mailbox_name=sprintf("\"%s\"",$this->utf_7_encode($mailbox) );
        $mailbox_base=sprintf("\"%s\"",$this->utf_7_encode($mailbox_base) );
        return $this->_genericCommand('LSUB', "$mailbox_base $mailbox_name" );
    }






    /**
     * Send the  APPEND  Command
     *
     * @return mixed Returns a PEAR_Error with an error message on any
     *               kind of failure, or true on success.
     * @access public
     * @since  1.0
     */
    function cmdAppend($mailbox, $msg , $flags_list = '' ,$time = '')
    {
        if(!$this->_connected){
            return new PEAR_Error('not connected!');
        }


        $cmdid=$this->_getCmdId();
        $msg_size=strlen($msg);

        $mailbox_name=sprintf("\"%s\"",$this->utf_7_encode($mailbox) );
        // TODO:
        // Falta el codigo para que flags list y time hagan algo!!
        if( $this->hasCapability( "LITERAL+" ) == true ){
            $param=sprintf("%s %s%s{%s+}\r\n%s",$mailbox_name,$flags_list,$time,$msg_size,$msg);
            if (PEAR::isError($error = $this->_putCMD($cmdid , 'APPEND' , $param ) ) ) {
                return $error;
            }
        }else{
            $param=sprintf("%s %s%s{%s}\r\n",$mailbox_name,$flags_list,$time,$msg_size);
            if (PEAR::isError($error = $this->_putCMD($cmdid , 'APPEND' , $param ) ) ) {
            return $error;
            }
            if (PEAR::isError($error = $this->_recvLn() ) ) {
            return $error;
            }

            if (PEAR::isError($error = $this->_send( $msg ) ) ) {
            return $error;
            }
        }


        $args=$this->_getRawResponse($cmdid);
        $ret = $this->_genericImapResponseParser($args,$cmdid);
        return $ret;
    }



    /**
     * Send the CLOSE command.
     *
     * @return mixed Returns a PEAR_Error with an error message on any
     *               kind of failure, or true on success.
     * @access public
     * @since  1.0
     */
    function cmdClose()
    {
        return $this->_genericCommand('CLOSE');
    }






    /**
     * Send the EXPUNGE command.
     *
     * @return mixed Returns a PEAR_Error with an error message on any
     *               kind of failure, or true on success.
     * @access public
     * @since  1.0
     */
    function cmdExpunge()
    {
        $ret=$this->_genericCommand('EXPUNGE');

        if(isset( $ret["PARSED"] ) ){
           $parsed=$ret["PARSED"];
            unset($ret["PARSED"]);
            foreach($parsed as $command){
                if( strtoupper($command["COMMAND"]) == 'EXPUNGE' ){
                        $ret["PARSED"][$command["COMMAND"]][]=$command["NRO"];
                }else{
                        $ret["PARSED"][$command["COMMAND"]]=$command["NRO"];
                }
            }
        }
        return $ret;
    }







    /**
     * Send the SEARCH command.
     *
     * @return mixed Returns a PEAR_Error with an error message on any
     *               kind of failure, or true on success.
     * @access public
     * @since  1.0
     */

    function cmdSearch($search_cmd)
    {
        /*        if($_charset != '' )
                    $_charset = "[$_charset] ";
                $param=sprintf("%s%s",$charset,$search_cmd);
        */
        $ret = $this->_genericCommand('SEARCH', $search_cmd );
        if(isset( $ret["PARSED"] ) ){
            $ret["PARSED"]=$ret["PARSED"][0]["EXT"];
        }
        return $ret;
    }






    /**
     * Send the STORE command.
     *
     * @param string $message_set  the sessage_set
     * @param string $dataitem: the way we store the flags
     *          FLAGS: replace the flags whith $value
     *          FLAGS.SILENT: replace the flags whith $value but don't return untagged responses
     *
     *          +FLAGS: Add the flags whith $value
     *          +FLAGS.SILENT: Add the flags whith $value but don't return untagged responses
     *
     *          -FLAGS: Remove the flags whith $value
     *          -FLAGS.SILENT: Remove the flags whith $value but don't return untagged responses
     *
     * @param string $value
     * @return mixed Returns a PEAR_Error with an error message on any
     *               kind of failure, or true on success.
     * @access public
     * @since  1.0
     */

    function cmdStore($message_set, $dataitem, $value)
    {
        /* As said in RFC2060...
        C: A003 STORE 2:4 +FLAGS (\Deleted)
        S: * 2 FETCH FLAGS (\Deleted \Seen)
        S: * 3 FETCH FLAGS (\Deleted)
        S: * 4 FETCH FLAGS (\Deleted \Flagged \Seen)
        S: A003 OK STORE completed
        */
        if( $dataitem!="FLAGS" && $dataitem!="FLAGS.SILENT" && $dataitem!="+FLAGS" &&
            $dataitem!="+FLAGS.SILENT" && $dataitem!="-FLAGS" && $dataitem!="-FLAGS.SILENT" ){
            $this->_prot_error("dataitem '$dataitem' is invalid! see RFC2060!!!!" , __LINE__ , __FILE__ );
        }
        $param=sprintf("%s %s (%s)",$message_set,$dataitem,$value);
        return $this->_genericCommand('STORE', $param );
    }







    /**
     * Send the COPY command.
     *
     * @return mixed Returns a PEAR_Error with an error message on any
     *               kind of failure, or true on success.
     * @access public
     * @since  1.0
     */

    function cmdCopy($message_set, $mailbox)
    {
        $mailbox_name=sprintf("\"%s\"",$this->utf_7_encode($mailbox) );
        return $this->_genericCommand('COPY', sprintf("%s %s",$message_set,$mailbox_name) );
    }













    function cmdUidFetch($msgset, $fetchparam)
    {
        return $this->_genericCommand('UID FETCH', sprintf("%s %s",$msgset,$fetchparam) );
    }








    function cmdUidCopy($message_set, $mailbox)
    {
        $mailbox_name=sprintf("\"%s\"",$this->utf_7_encode($mailbox) );
        return $this->_genericCommand('UID COPY', sprintf("%s %s",$message_set,$mailbox_name) );
    }








     /**
     * Send the UID STORE command.
     *
     * @param string $message_set  the sessage_set
     * @param string $dataitem: the way we store the flags
     *          FLAGS: replace the flags whith $value
     *          FLAGS.SILENT: replace the flags whith $value but don't return untagged responses
     *
     *          +FLAGS: Add the flags whith $value
     *          +FLAGS.SILENT: Add the flags whith $value but don't return untagged responses
     *
     *          -FLAGS: Remove the flags whith $value
     *          -FLAGS.SILENT: Remove the flags whith $value but don't return untagged responses
     *
     * @param string $value
     * @return mixed Returns a PEAR_Error with an error message on any
     *               kind of failure, or true on success.
     * @access public
     * @since  1.0
     */

    function cmdUidStore($message_set, $dataitem, $value)
    {
        /* As said in RFC2060...
        C: A003 STORE 2:4 +FLAGS (\Deleted)
        S: * 2 FETCH FLAGS (\Deleted \Seen)
        S: * 3 FETCH FLAGS (\Deleted)
        S: * 4 FETCH FLAGS (\Deleted \Flagged \Seen)
        S: A003 OK STORE completed
        */
        if( $dataitem!="FLAGS" && $dataitem!="FLAGS.SILENT" && $dataitem!="+FLAGS" &&
            $dataitem!="+FLAGS.SILENT" && $dataitem!="-FLAGS" && $dataitem!="-FLAGS.SILENT" ){
            $this->_prot_error("dataitem '$dataitem' is invalid! see RFC2060!!!!" , __LINE__ , __FILE__ );
        }
        return $this->_genericCommand('UID STORE', sprintf("%s %s (%s)",$message_set,$dataitem,$value) );
    }










    /**
     * Send the SEARCH command.
     *
     * @return mixed Returns a PEAR_Error with an error message on any
     *               kind of failure, or true on success.
     * @access public
     * @since  1.0
     */

    function cmdUidSearch($search_cmd)
    {
        $ret=$this->_genericCommand('UID SEARCH', sprintf("%s",$search_cmd) );
        if(isset( $ret["PARSED"] ) ){
            $ret["PARSED"]=$ret["PARSED"][0]["EXT"];
        }
        return $ret;
    }











    /**
     * Send the X command.
     *
     * @return mixed Returns a PEAR_Error with an error message on any
     *               kind of failure, or true on success.
     * @access public
     * @since  1.0
     */

    function cmdX($atom, $parameters)
    {
        return $this->_genericCommand("X$atom", $parameters );
    }








/********************************************************************
***
***             HERE ENDS the RFC2060 IMAPS FUNCTIONS
***             AND BEGIN THE EXTENSIONS FUNCTIONS
***
********************************************************************/







/********************************************************************
***             RFC2087 IMAP4 QUOTA extension BEGINS HERE
********************************************************************/


    /**
     * Send the GETQUOTA command.
     *
     * @param string $mailbox_name the mailbox name to query for quota data
     * @return mixed Returns a PEAR_Error with an error message on any
     *               kind of failure, or quota data on success
     * @access public
     * @since  1.0
     */

    function cmdGetQuota($mailbox_name)
    {


        //Check if the IMAP server has QUOTA support
        if( ! $this->hasQuotaSupport() ){
            return new PEAR_Error("This IMAP server does not support QUOTA's! ");
        }
        $mailbox_name=sprintf("%s",$this->utf_7_encode($mailbox_name) );
        $ret = $this->_genericCommand('GETQUOTA', $mailbox_name );
        if(isset( $ret["PARSED"] ) ){
        // remove the array index because the quota response returns only 1 line of output
            $ret['PARSED']=$ret["PARSED"][0];
        }
        return $ret;
    }


    /**
     * Send the GETQUOTAROOT command.
     *
     * @param string $mailbox_name the mailbox name to query for quota data
     * @return mixed Returns a PEAR_Error with an error message on any
     *               kind of failure, or quota data on success
     * @access public
     * @since  1.0
     */

    function cmdGetQuotaRoot($mailbox_name)
    {
        //Check if the IMAP server has QUOTA support
        if( ! $this->hasQuotaSupport() ){
            return new PEAR_Error("This IMAP server does not support QUOTA's! ");
        }
        $mailbox_name=sprintf("%s",$this->utf_7_encode($mailbox_name) );
        $ret = $this->_genericCommand('GETQUOTAROOT', $mailbox_name );

        if(isset( $ret["PARSED"] ) ){
        // remove the array index because the quota response returns only 1 line of output
            $ret['PARSED']=$ret["PARSED"][0];
        }
        return $ret;
    }




    /**
     * Send the SETQUOTA command.
     *
     * @param string $mailbox_name the mailbox name to query for quota data
     * @param string $storageQuota sets the max number of bytes this mailbox can handle
     * @param string $messagesQuota sets the max number of messages this mailbox can handle
     * @return mixed Returns a PEAR_Error with an error message on any
     *               kind of failure, or quota data on success
     * @access public
     * @since  1.0
     */
// TODO:  implement the quota by number of emails!!
    function cmdSetQuota($mailbox_name, $storageQuota = null ,$messagesQuota = null )
    {
        //Check if the IMAP server has QUOTA support
        if( ! $this->hasQuotaSupport() ){
            return new PEAR_Error("This IMAP server does not support QUOTA's! ");
        }

        if( ($messagesQuota == null) && ( $storageQuota == null) ){
            return new PEAR_Error('$storageQuota and $messagesQuota parameters can\'t be both null if you want to use quota');
        }
        $mailbox_name=sprintf("\"%s\"",$this->utf_7_encode($mailbox_name) );
        //Make the command request
        $param=sprintf("%s (",$mailbox_name);
        if($storageQuota != null ){
            $param=sprintf("%sSTORAGE %s",$param,$storageQuota);
            if( $messagesQuota != null ){
                //if we have both types of quota on the same call we must append an space between
                // those parameters
                $param=sprintf("%s ",$param);
            }
        }
        if($messagesQuota != null ){
            $param=sprintf("%sMESSAGES %s",$param,$messagesQuota);

        }
        $param=sprintf("%s)",$param);

        return $this->_genericCommand('SETQUOTA', $param );
    }



    /**
     * Send the SETQUOTAROOT command.
     *
     * @param string $mailbox_name the mailbox name to query for quota data
     * @param string $storageQuota sets the max number of bytes this mailbox can handle
     * @param string $messagesQuota sets the max number of messages this mailbox can handle
     * @return mixed Returns a PEAR_Error with an error message on any
     *               kind of failure, or quota data on success
     * @access public
     * @since  1.0
     */
    function cmdSetQuotaRoot($mailbox_name, $storageQuota = null ,$messagesQuota = null)
    {
        //Check if the IMAP server has QUOTA support
        if( ! $this->hasQuotaSupport() ){
            return new PEAR_Error("This IMAP server does not support QUOTA's! ");
        }

        if( ($messagesQuota == null) && ( $storageQuota == null) ){
            return new PEAR_Error('$storageQuota and $messagesQuota parameters can\'t be both null if you want to use quota');
        }
        $mailbox_name=sprintf("\"%s\"",$this->utf_7_encode($mailbox_name) );
        //Make the command request
        $param=sprintf("%s (",$mailbox_name);
        if($storageQuota != null ){
            $param=sprintf("%sSTORAGE %s",$param,$storageQuota);
            if( $messagesQuota != null ){
                //if we have both types of quota on the same call we must append an space between
                // those parameters
                $param=sprintf("%s ",$param);
            }
        }
        if($messagesQuota != null ){
            $param=sprintf("%sMESSAGES %s",$param,$messagesQuota);

        }
        $param=sprintf("%s)",$param);

        return $this->_genericCommand('SETQUOTAROOT', $param );
    }



/********************************************************************
***             RFC2087 IMAP4 QUOTA extension ENDS HERE
********************************************************************/






/********************************************************************
***             RFC2086 IMAP4 ACL extension BEGINS HERE
********************************************************************/




    function cmdSetACL($mailbox_name, $user, $acl)
    {

        //Check if the IMAP server has ACL support
        if( ! $this->hasAclSupport() ){
            return new PEAR_Error("This IMAP server does not support ACL's! ");
        }
        $mailbox_name=sprintf("\"%s\"",$this->utf_7_encode($mailbox_name) );
        $user_name=sprintf("\"%s\"",$this->utf_7_encode($user) );
        if(is_array($acl)){
            $acl=implode('',$acl);
        }
        return $this->_genericCommand('SETACL', sprintf("%s %s \"%s\"",$mailbox_name,$user_name,$acl) );
    }






    function cmdDeleteACL($mailbox_name, $user)
    {
        //Check if the IMAP server has ACL support
        if( ! $this->hasAclSupport() ){
            return new PEAR_Error("This IMAP server does not support ACL's! ");
        }
        $mailbox_name=sprintf("\"%s\"",$this->utf_7_encode($mailbox_name) );
        
        return $this->_genericCommand('DELETEACL', sprintf("%s \"%s\"",$mailbox_name,$user) );
    }









    function cmdGetACL($mailbox_name)
    {
        //Check if the IMAP server has ACL support
        if( ! $this->hasAclSupport() ){
            return new PEAR_Error("This IMAP server does not support ACL's! ");
        }
        $mailbox_name=sprintf("\"%s\"",$this->utf_7_encode($mailbox_name) );
        $ret = $this->_genericCommand('GETACL', sprintf("%s",$mailbox_name) );
        if(isset( $ret["PARSED"] ) ){
            $ret['PARSED']=$ret["PARSED"][0]["EXT"];

        }
        return $ret;
   }







    function cmdListRights($mailbox_name, $user)
    {
        //Check if the IMAP server has ACL support
        if( ! $this->hasAclSupport() ){
            return new PEAR_Error("This IMAP server does not support ACL's! ");
        }
        $mailbox_name=sprintf("\"%s\"",$this->utf_7_encode($mailbox_name) );
        $ret = $this->_genericCommand('LISTRIGHTS', sprintf("%s \"%s\"",$mailbox_name,$user) );
        if(isset( $ret["PARSED"] ) ){
            $ret["PARSED"]=$ret["PARSED"][0]["EXT"];
        }
        return $ret;
    }









    function cmdMyRights($mailbox_name)
    {
        //Check if the IMAP server has ACL support
        if( ! $this->hasAclSupport() ){
            return new PEAR_Error("This IMAP server does not support ACL's! ");
        }
        $mailbox_name=sprintf("\"%s\"",$this->utf_7_encode($mailbox_name) );
        $ret = $this->_genericCommand('MYRIGHTS', sprintf("%s",$mailbox_name) );
        if(isset( $ret["PARSED"] ) ){
            $ret["PARSED"]=$ret["PARSED"][0]["EXT"];
        }
        return $ret;
    }


/********************************************************************
***             RFC2086 IMAP4 ACL extension ENDs HERE
********************************************************************/












/*******************************************************************************
***  draft-daboo-imap-annotatemore-05 IMAP4 ANNOTATEMORE extension BEGINS HERE
********************************************************************************/



    function cmdSetAnnotation($mailbox_name, $entry, $values)
    {
        // Check if the IMAP server has ANNOTATEMORE support
        if(!$this->hasAnnotateMoreSupport()) {
            return new PEAR_Error('This IMAP server does not support the ANNOTATEMORE extension!');
        }
        if (!is_array($values)) {
            return new PEAR_Error('Invalid $values argument passed to cmdSetAnnotation');
        }

        $vallist = '';
        foreach ($values as $name => $value) {
            $vallist .= "\"$name\" \"$value\" ";
        }
        $vallist = rtrim($vallist);

        return $this->_genericCommand('SETANNOTATION', sprintf('"%s" "%s" (%s)', $mailbox_name, $entry, $vallist));
    }













    function cmdDeleteAnnotation($mailbox_name, $entry, $values)
    {
        // Check if the IMAP server has ANNOTATEMORE support
        if(!$this->hasAnnotateMoreSupport()) {
            return new PEAR_Error('This IMAP server does not support the ANNOTATEMORE extension!');
        }
        if (!is_array($values)) {
            return new PEAR_Error('Invalid $values argument passed to cmdDeleteAnnotation');
        }

        $vallist = '';
        foreach ($values as $name) {
            $vallist .= "\"$name\" NIL ";
        }
        $vallist = rtrim($vallist);

        return $this->_genericCommand('SETANNOTATION', sprintf('"%s" "%s" (%s)', $mailbox_name, $entry, $vallist));
    }












    function cmdGetAnnotation($mailbox_name, $entries, $values)
    {
        // Check if the IMAP server has ANNOTATEMORE support
        if(!$this->hasAnnotateMoreSupport()) {
            return new PEAR_Error('This IMAP server does not support the ANNOTATEMORE extension!');
        }

        $entlist = '';

       if (!is_array($entries)) {
            $entries = array($entries);
       }

        foreach ($entries as $name) {
            $entlist .= "\"$name\" ";
        }
        $entlist = rtrim($entlist);
        if (count($entries) > 1) {
            $entlist = "($entlist)";
        }



        $vallist = '';
        if (!is_array($values)) {
            $values = array($values);
        }

        foreach ($values as $name) {
            $vallist .= "\"$name\" ";
        }
        $vallist = rtrim($vallist);
        if (count($values) > 1) {
            $vallist = "($vallist)";
        }

        return $this->_genericCommand('GETANNOTATION', sprintf('"%s" %s %s', $mailbox_name, $entlist, $vallist));
   }


/*****************************************************************************
***  draft-daboo-imap-annotatemore-05 IMAP4 ANNOTATEMORE extension ENDs HERE
******************************************************************************/







/********************************************************************
***
***             HERE ENDS THE EXTENSIONS FUNCTIONS
***             AND BEGIN THE AUXILIARY FUNCTIONS
***
********************************************************************/





    /**
    * tell if the server has capability $capability
    *
    * @return true or false
    *
    * @access public
    * @since  1.0
    */
    function getServerAuthMethods()
    {
        if( $this->_serverAuthMethods == null ){
            $this->cmdCapability();
            return $this->_serverAuthMethods;
        }
        return false;
    }







    /**
    * tell if the server has capability $capability
    *
    * @return true or false
    *
    * @access public
    * @since  1.0
    */
    function hasCapability($capability)
    {
        if( $this->_serverSupportedCapabilities == null ){
            $this->cmdCapability();
        }
        if($this->_serverSupportedCapabilities != null ){
            if( in_array( $capability , $this->_serverSupportedCapabilities ) ){
                return true;
            }
        }
        return false;
    }



    /**
    * tell if the server has Quota support
    *
    * @return true or false
    *
    * @access public
    * @since  1.0
    */
    function hasQuotaSupport()
    {
        return $this->hasCapability('QUOTA');
    }





    /**
    * tell if the server has Quota support
    *
    * @return true or false
    *
    * @access public
    * @since  1.0
    */
    function hasAclSupport()
    {
        return $this->hasCapability('ACL');
    }





    /**
    * tell if the server has support for the ANNOTATEMORE extension
    *
    * @return true or false
    *
    * @access public
    * @since  1.0
    */
    function hasAnnotateMoreSupport()
    {
        return $this->hasCapability('ANNOTATEMORE');
    }










    /**
    * Parses the responses like RFC822.SIZE and INTERNALDATE
    *
    * @param string the IMAP's server response
    *
    * @return string containing  the parsed response
    * @access private
    * @since  1.0
    */

    function _parseOneStringResponse(&$str, $line,$file)
    {
        $this->_parseSpace($str , $line , $file );
        $size = $this->_getNextToken($str,$uid);
        return $uid;
    }


    /**
    * Parses the FLAG response
    *
    * @param string the IMAP's server response
    *
    * @return Array containing  the parsed  response
    * @access private
    * @since  1.0
    */
    function _parseFLAGSresponse(&$str)
    {
        $this->_parseSpace($str , __LINE__ , __FILE__ );
        $params_arr[] = $this->_arrayfy_content($str);
        $flags_arr=array();
        for( $i = 0 ; $i < count($params_arr[0]) ; $i++ ){
            $flags_arr[] = $params_arr[0][$i];
        }
        return $flags_arr;
    }





    /**
    * Parses the BODY response
    *
    * @param string the IMAP's server response
    *
    * @return Array containing  the parsed  response
    * @access private
    * @since  1.0
    */

    function _parseBodyResponse(&$str, $command){

            $this->_parseSpace($str , __LINE__ , __FILE__ );
            while($str[0] != ')' && $str!=''){
                $params_arr[] = $this->_arrayfy_content($str);
            }

            return $params_arr;
    }






    /**
    * Makes the content an Array
    *
    * @param string the IMAP's server response
    *
    * @return Array containing  the parsed  response
    * @access private
    * @since  1.0
    */
    function _arrayfy_content(&$str)
    {
        $params_arr=array();
        $this->_getNextToken($str,$params);
        if($params != '(' ){
            return $params;
        }
        $this->_getNextToken($str,$params,false,false);
        while ( $str != '' && $params != ')'){
                if($params != '' ){
                    if($params[0] == '(' ){
                        $params=$this->_arrayfy_content( $params );
                    }
                    if($params != ' ' ){
                        //I don't remove the colons (") to handle the case of retriving " "
                        // If I remove the colons the parser will interpret this field as an imap separator (space)
                        // instead of a valid field so I remove the colons here
                        if($params=='""'){
                            $params='';
                        }else{
                            if($params[0]=='"'){
                                $params=substr($params,1,strlen($params)-2);
                            }
                        }
                        $params_arr[]=$params;
                    }
                }else{
                    //if params if empty (for example i'm parsing 2 quotes ("")
                    // I'll append an array entry to mantain compatibility
                    $params_arr[]=$params;
                }
                $this->_getNextToken($str,$params,false,false);
        }
        return $params_arr;
    }




    /**
    * Parses the BODY[],BODY[TEXT],.... responses
    *
    * @param string the IMAP's server response
    *
    * @return Array containing  the parsed  response
    * @access private
    * @since  1.0
    */
    function _parseContentresponse(&$str, $command)
    {
        $content = '';
        $this->_parseSpace($str , __LINE__ , __FILE__ );
        $size  =$this->_getNextToken($str,$content);
        return array( "CONTENT"=> $content , "CONTENT_SIZE" =>$size );
    }








    /**
    * Parses the ENVELOPE response
    *
    * @param string the IMAP's server response
    *
    * @return Array containing  the parsed  response
    * @access private
    * @since  1.0
    */
    function _parseENVELOPEresponse(&$str)
    {
        $content = '';
        $this->_parseSpace($str , __LINE__ , __FILE__ );

        $this->_getNextToken($str,$parenthesis);
        if( $parenthesis != '(' ){
                $this->_prot_error("must be a '(' but is a '$parenthesis' !!!!" , __LINE__ , __FILE__ );
        }
        // Get the email's Date
        $this->_getNextToken($str,$date);

        $this->_parseSpace($str , __LINE__ , __FILE__ );

        // Get the email's Subject:
        $this->_getNextToken($str,$subject);
        //$subject=$this->decode($subject);

        $this->_parseSpace($str , __LINE__ , __FILE__ );

        //FROM LIST;
        $from_arr = $this->_getAddressList($str);

        $this->_parseSpace($str , __LINE__ , __FILE__ );

        //"SENDER LIST\n";
        $sender_arr = $this->_getAddressList($str);

        $this->_parseSpace($str , __LINE__ , __FILE__ );

        //"REPLY-TO LIST\n";
        $reply_to_arr=$this->_getAddressList($str);

        $this->_parseSpace($str , __LINE__ , __FILE__ );

        //"TO LIST\n";
        $to_arr = $this->_getAddressList($str);

        $this->_parseSpace($str , __LINE__ , __FILE__ );

        //"CC LIST\n";
        $cc_arr = $this->_getAddressList($str);

        $this->_parseSpace($str , __LINE__ , __FILE__ );

        //"BCC LIST|$str|\n";
        $bcc_arr = $this->_getAddressList($str);

        $this->_parseSpace($str , __LINE__ , __FILE__ );

        $this->_getNextToken($str,$in_reply_to);

        $this->_parseSpace($str , __LINE__ , __FILE__ );

        $this->_getNextToken($str,$message_id);

        $this->_getNextToken($str,$parenthesis);

        if( $parenthesis != ')' ){
            $this->_prot_error("must be a ')' but is a '$parenthesis' !!!!" , __LINE__ , __FILE__ );
        }

        return array( "DATE"=> $date , "SUBJECT" => $subject,"FROM" => $from_arr,
            "SENDER" => $sender_arr , "REPLY_TO" => $reply_to_arr, "TO" => $to_arr,
            "CC" =>$cc_arr, "BCC"=> $bcc_arr, "IN_REPLY_TO" =>$in_reply_to, "MESSAGE_ID"=>$message_id  );
    }





    /**
    * Parses the ARRDLIST as defined in RFC
    *
    * @param string the IMAP's server response
    *
    * @return Array containing  the parsed  response
    * @access private
    * @since  1.0
    */
    function _getAddressList(&$str)
    {
        $params_arr = $this->_arrayfy_content($str);
        if( !isset( $params_arr ) ){
            return $params_arr;
        }


        if( is_array($params_arr) ){
            $personal_name  = $params_arr[0][0];
            $at_domain_list = $params_arr[0][1];
            $mailbox_name   = $params_arr[0][2];
            $host_name      = $params_arr[0][3];
            if( $mailbox_name!='' && $host_name!='' ){
                $email=$mailbox_name . "@" . $host_name;
            }else{
                $email=false;
            }
            if($email==false){
                $rfc822_email=false;
            }else{
                if(!isset($personal_name)){
                    $rfc822_email= "<". $email . ">";
                }else{
                    $rfc822_email= "\"". $personal_name ."\" <". $email . ">";
                }
            }
            $email_arr[] = array ( "PERSONAL_NAME"=> $personal_name , "AT_DOMAIN_LIST"=>$at_domain_list ,
                                   "MAILBOX_NAME"=> $this->utf_7_decode($mailbox_name), "HOST_NAME"=> $host_name,
                                   "EMAIL"=>$email , "RFC822_EMAIL" => $rfc822_email );
            return $email_arr;
        }

        return array();
    }







    /**
    * Utility funcion to find the closing parenthesis ")" Position it takes care of quoted ones
    *
    * @param string the IMAP's server response
    *
    * @return int containing  the pos of the closing parenthesis ")"
    * @access private
    * @since  1.0
    */
    function _getClosingBracesPos($str_line, $startDelim ='(', $stopDelim = ')' )
    {
        $len = strlen( $str_line );
        $pos = 0;
        // ignore all extra characters
        // If inside of a string, skip string -- Boundary IDs and other
        // things can have ) in them.
        if ( $str_line[$pos] != $startDelim ) {
            $this->_prot_error("_getClosingParenthesisPos: must start with a '(' but is a '". $str_line[$pos] ."'!!!!\n" .
                "STR_LINE:$str_line|size:$len|POS: $pos\n" , __LINE__ , __FILE__ );
            return( $len );
        }
        for( $pos = 1 ; $pos < $len ; $pos++ ){
            if ($str_line[$pos] == $stopDelim ) {
                break;
            }
            if ($str_line[$pos] == '"') {
                $pos++;
                while ( $str_line[$pos] != '"' && $pos < $len ) {
                    if ($str_line[$pos] == "\\" && $str_line[$pos + 1 ] == '"' )
                        $pos++;
                    if ($str_line[$pos] == "\\" && $str_line[$pos + 1 ] == "\\" )
                        $pos++;
                    $pos++;
                }
            }
            if ( $str_line[$pos] == $startDelim ) {
                $str_line_aux = substr( $str_line , $pos );
                $pos_aux = $this->_getClosingBracesPos( $str_line_aux );
                $pos+=$pos_aux;
            }
        }
        if( $str_line[$pos] != $stopDelim ){
            $this->_prot_error("_getClosingBracesPos: must be a $stopDelim but is a '". $str_line[$pos] ."'|POS:$pos|STR_LINE:$str_line!!!!" , __LINE__ , __FILE__ );
        }

        if( $pos >= $len )
            return false;
        return $pos;
    }






    /**
    * Utility funcion to get from here to the end of the line
    *
    * @param string the IMAP's server response
    *
    * @return string containing  the string to the end of the line
    * @access private
    * @since  1.0
    */

    function _getToEOL(&$str , $including = true)
    {
        $len = strlen( $str );
        if( $including ){
            for($i=0;$i<$len;$i++){
                if( $str[$i] =="\n" )
                    break;
            }
            $content=substr($str,0,$i + 1);
            $str=substr($str,$i + 1);
            return $content;

        }else{
            for( $i = 0 ; $i < $len ; $i++ ){
                if( $str[$i] =="\n" || $str[$i] == "\r")
                    break;
            }
            $content = substr( $str ,0 , $i );
            $str = substr( $str , $i );
            return $content;
        }
    }




    /**
    * Fetches the next IMAP token or parenthesis
    *
    * @param string the IMAP's server response
    * @param string the next token
    * @param boolean true: the parenthesis IS a token, false: I consider
    *        all the response in parenthesis as a token
    *
    * @return int containing  the content size
    * @access private
    * @since  1.0
    */


    function _getNextToken(&$str, &$content, $parenthesisIsToken=true,$colonIsToken=true){
        $len = strlen($str);
        $pos = 0;
        $content_size = false;
        $content = false;
        if($str == '' || $len < 2 ){
            $content=$str;
            return $len;
        }
        switch( $str[0] ){
        case '{':
            if( ($posClosingBraces = $this->_getClosingBracesPos($str, '{' , '}' )) == false ){
                $this->_prot_error("_getClosingBracesPos() error!!!" , __LINE__ , __FILE__ );
            }
            if(! is_numeric( ( $strBytes = substr( $str , 1 , $posClosingBraces - 1) ) ) ){
                $this->_prot_error("must be a number but is a '" . $strBytes ."'!!!!" , __LINE__ , __FILE__ );
            }
            if( $str[$posClosingBraces] != '}' ){
                $this->_prot_error("must be a '}'  but is a '" . $str[$posClosingBraces] ."'!!!!" , __LINE__ , __FILE__ );
            }
            if( $str[$posClosingBraces + 1] != "\r" ){
                $this->_prot_error("must be a '\\r'  but is a '" . $str[$posClosingBraces + 1] ."'!!!!" , __LINE__ , __FILE__ );
            }
            if( $str[$posClosingBraces + 2] != "\n" ){
                $this->_prot_error("must be a '\\n'  but is a '" . $str[$posClosingBraces + 2] ."'!!!!" , __LINE__ , __FILE__ );
            }
            $content = substr( $str , $posClosingBraces + 3 , $strBytes );
            if( strlen( $content ) != $strBytes ){
                $this->_prot_error("content size is ". strlen($content) . " but the string reports a size of $strBytes!!!\n" , __LINE__ , __FILE__ );
            }
            $content_size = $strBytes;
            //Advance the string
            $str = substr( $str , $posClosingBraces + $strBytes + 3 );
            break;
        case '"':
            if($colonIsToken){
                for($pos=1;$pos<$len;$pos++){
                    if ( $str[$pos] == "\"" ) {
                        break;
                    }
                    if ($str[$pos] == "\\" && $str[$pos + 1 ] == "\"" )
                        $pos++;
                    if ($str[$pos] == "\\" && $str[$pos + 1 ] == "\\" )
                        $pos++;
                }
                if($str[$pos] != '"' ){
                    $this->_prot_error("must be a '\"'  but is a '" . $str[$pos] ."'!!!!" , __LINE__ , __FILE__ );
                }
                $content_size = $pos;
                $content = substr( $str , 1 , $pos - 1 );
                //Advance the string
                $str = substr( $str , $pos + 1 );
            }else{
                for($pos=1;$pos<$len;$pos++){
                    if ( $str[$pos] == "\"" ) {
                        break;
                    }
                    if ($str[$pos] == "\\" && $str[$pos + 1 ] == "\"" )
                        $pos++;
                    if ($str[$pos] == "\\" && $str[$pos + 1 ] == "\\" )
                        $pos++;
                }
                if($str[$pos] != '"' ){
                    $this->_prot_error("must be a '\"'  but is a '" . $str[$pos] ."'!!!!" , __LINE__ , __FILE__ );
                }
                $content_size = $pos;
                $content = substr( $str , 0 , $pos + 1 );
                //Advance the string
                $str = substr( $str , $pos + 1 );

            }
            break;

        case "\r":
            $pos = 1;
            if( $str[1] == "\n")
                $pos++;
            $content_size = $pos;
            $content = substr( $str , 0 , $pos );
            $str = substr( $str , $pos );
            break;
        case "\n":
            $pos = 1;
            $content_size = $pos;
            $content = substr( $str , 0 , $pos );
            $str = substr( $str , $pos );
            break;
        case '(':
            if( $parenthesisIsToken == false ){
                $pos = $this->_getClosingBracesPos( $str );
                $content_size = $pos + 1;
                $content = substr( $str , 0 , $pos + 1 );
                $str = substr( $str , $pos + 1 );
            }else{
                $pos = 1;
                $content_size = $pos;
                $content = substr( $str , 0 , $pos );
                $str = substr( $str , $pos );
            }
            break;
        case ')':
            $pos = 1;
            $content_size = $pos;
            $content = substr( $str , 0 , $pos );
            $str = substr( $str , $pos );
            break;
        case ' ':
            $pos = 1;
            $content_size = $pos;
            $content = substr( $str , 0 , $pos );
            $str = substr( $str , $pos );
            break;
        default:
            for( $pos = 0 ; $pos < $len ; $pos++ ){
                if ( $str[$pos] == ' ' || $str[$pos] == "\r" || $str[$pos] == ')' || $str[$pos] == '(' || $str[$pos] == "\n" ) {
                    break;
                }
                if ( $str[$pos] == "\\" && $str[$pos + 1 ] == ' '  )
                    $pos++;
                if ( $str[$pos] == "\\" && $str[$pos + 1 ] == "\\" )
                    $pos++;
            }
            //Advance the string
            if( $pos == 0 ){
                $content_size = 1;
                $content = substr( $str , 0 , 1 );
                $str = substr( $str , 1 );
            }else{
                $content_size = $pos;
                $content = substr( $str , 0 , $pos );
                if($pos < $len){
                    $str = substr( $str , $pos  );
                }else{
                //if this is the end of the string... exit the switch
                    break;
                }


            }
            break;
        }
        return $content_size;
    }






    /**
    * Utility funcion to display to console the protocol errors
    *
    * @param string the error
    * @param int the line producing the error
    * @param string file where the error was produced
    *
    * @return string containing  the error
    * @access private
    * @since  1.0
    */
    function _prot_error($str , $line , $file,$printError=true)
    {
        if($printError){
            echo "$line,$file,PROTOCOL ERROR!:$str\n";
        }
    }







    function _getEXTarray(&$str , $startDelim = '(' , $stopDelim = ')'){
        /* I let choose the $startDelim  and $stopDelim to allow parsing
           the OK response  so I also can parse a response like this
           * OK [UIDNEXT 150] Predicted next UID
        */
        $this->_getNextToken( $str , $parenthesis );
        if( $parenthesis != $startDelim ){
            $this->_prot_error("must be a '$startDelim' but is a '$parenthesis' !!!!" , __LINE__ , __FILE__ );
        }
        $parenthesis = '';
        $struct_arr = array();
        while( $parenthesis != $stopDelim && $str != '' ){
            // The command
            $this->_getNextToken( $str , $token );
            $token = strtoupper( $token );

            if( ( $ret = $this->_retrParsedResponse( $str , $token ) ) != false ){
                //$struct_arr[$token] = $ret;
                $struct_arr=array_merge($struct_arr, $ret);
            }

            $parenthesis=$token;

        }//While

       if( $parenthesis != $stopDelim  ){
            $this->_prot_error("1_must be a '$stopDelim' but is a '$parenthesis' !!!!" , __LINE__ , __FILE__ );
       }
        return $struct_arr;
    }





    /* EmailReporting: Changed $command to $token as it causes and error on logout */
    function _retrParsedResponse( &$str , $token, $previousToken = null)
    {

    //echo "\n\nTOKEN:$token\r\n";
        switch( $token ){
        case "RFC822.SIZE" :
            return array($token=>$this->_parseOneStringResponse( $str,__LINE__ , __FILE__ ));
            break;
//        case "RFC822.TEXT" :

//        case "RFC822.HEADER" :


        case "RFC822" :
            return array($token=>$this->_parseContentresponse( $str , $token ));
            break;
        case "FLAGS" :

        case "PERMANENTFLAGS" :
            return array($token=>$this->_parseFLAGSresponse( $str ));
            break;

        case "ENVELOPE" :
            return array($token=>$this->_parseENVELOPEresponse( $str ));
            break;
        case "EXPUNGE" :
            return false;
            break;

        case "UID" :

        case "UIDNEXT" :

        case "UIDVALIDITY" :

        case "UNSEEN" :

        case "MESSAGES" :

        case "UIDNEXT" :

        case "UIDVALIDITY" :

        case "UNSEEN" :

        case "INTERNALDATE" :
            return array($token=>$this->_parseOneStringResponse( $str,__LINE__ , __FILE__ ));
            break;
        case "BODY" :

        case "BODYSTRUCTURE" :
            return array($token=>$this->_parseBodyResponse( $str , $token ));
            break;
        case "RECENT" :
            if( $previousToken != null ){
                $aux["RECENT"]=$previousToken;
                return $aux;
            }else{
                return array($token=>$this->_parseOneStringResponse( $str,__LINE__ , __FILE__ ));
            }
            break;

        case "EXISTS" :
            return array($token=>$previousToken);
            break;
        case "READ-WRITE" :

        case "READ-ONLY" :
            return array($token=>$token);
            break;
        case "QUOTA" :
            /*
            A tipical GETQUOTA DIALOG IS AS FOLLOWS

                C: A0004 GETQUOTA user.damian
                S: * QUOTA user.damian (STORAGE 1781460 4000000)
                S: A0004 OK Completed
            */

            $mailbox = $this->_parseOneStringResponse( $str,__LINE__ , __FILE__ );
            $this->_parseSpace( $str , __LINE__ , __FILE__ );
            $this->_parseString( $str , '(' , __LINE__ , __FILE__ );

            $ret_aux = array("MAILBOX"=>$this->utf_7_decode($mailbox) );
            $this->_getNextToken( $str , $quota_resp );
            if( ( $ext = $this->_retrParsedResponse( $str , $quota_resp )) == false){
                    $this->_prot_error("bogus response!!!!" , __LINE__ , __FILE__ );
            }
            $ret_aux=array_merge($ret_aux,$ext);

            $this->_getNextToken( $str , $separator );
            if( $separator == ')' ){
                return array($token=>$ret_aux);
            }


            $this->_parseSpace( $str , __LINE__ , __FILE__ );

            $this->_getNextToken( $str , $quota_resp );
            if( ( $ext = $this->_retrParsedResponse( $str , $quota_resp )) == false){
                    $this->_prot_error("bogus response!!!!" , __LINE__ , __FILE__ );
            }
            $ret_aux=array_merge($ret_aux,$ext);

            $this->_parseString( $str , ')' , __LINE__ , __FILE__ );
            return array($token=>$ret_aux);
            break;

        case "QUOTAROOT" :
            /*
            A tipical GETQUOTA DIALOG IS AS FOLLOWS

                C: A0004 GETQUOTA user.damian
                S: * QUOTA user.damian (STORAGE 1781460 4000000)
                S: A0004 OK Completed
            */
            $mailbox = $this->utf_7_decode($this->_parseOneStringResponse( $str,__LINE__ , __FILE__ ));

            $str_line = rtrim( substr( $this->_getToEOL( $str , false ) , 0 ) );

            $quotaroot = $this->_parseOneStringResponse( $str_line,__LINE__ , __FILE__ );
            $ret = @array( "MAILBOX"=>$this->utf_7_decode($mailbox) , $token=>$quotaroot );
            return array($token=>$ret);
            break;
        case "STORAGE" :
                $used = $this->_parseOneStringResponse( $str,__LINE__ , __FILE__ );
                $qmax = $this->_parseOneStringResponse( $str,__LINE__ , __FILE__ );
                return array($token=>array("USED"=> $used, "QMAX" => $qmax));
        break;
        case "MESSAGE" :
                $mused = $this->_parseOneStringResponse( $str,__LINE__ , __FILE__ );
                $mmax = $this->_parseOneStringResponse( $str,__LINE__ , __FILE__ );
                return array($token=>array("MUSED"=> $mused, "MMAX" => $mmax));
        break;
        case "FETCH" :
                $this->_parseSpace( $str  ,__LINE__  ,__FILE__ );
                // Get the parsed pathenthesis
                $struct_arr = $this->_getEXTarray( $str );
                return $struct_arr;
            break;
        case "CAPABILITY" :
                $this->_parseSpace( $str , __LINE__ , __FILE__ );
                $str_line = rtrim( substr( $this->_getToEOL( $str , false ) , 0 ) );
                $struct_arr["CAPABILITIES"] = explode( ' ' , $str_line );
                return array($token=>$struct_arr);
            break;
        case "STATUS" :
                $mailbox = $this->_parseOneStringResponse( $str,__LINE__ , __FILE__ );
                $this->_parseSpace( $str , __LINE__ , __FILE__ );
                $ext = $this->_getEXTarray( $str );
                $struct_arr["MAILBOX"] = $this->utf_7_decode($mailbox);
                $struct_arr["ATTRIBUTES"] = $ext;
                return array($token=>$struct_arr);
            break;
        case "LIST" :
                $this->_parseSpace( $str , __LINE__ , __FILE__ );
                $params_arr = $this->_arrayfy_content( $str );

                $this->_parseSpace( $str , __LINE__ , __FILE__ );
                $this->_getNextToken( $str , $hierarchydelim );

                $this->_parseSpace( $str,__LINE__ , __FILE__);
                $this->_getNextToken( $str , $mailbox_name );

                $result_array = array( "NAME_ATTRIBUTES"=>$params_arr , "HIERACHY_DELIMITER"=>$hierarchydelim , "MAILBOX_NAME"=>  $this->utf_7_decode($mailbox_name) );
                return array($token=>$result_array);
            break;
        case "LSUB" :
                $this->_parseSpace( $str , __LINE__ , __FILE__ );
                $params_arr = $this->_arrayfy_content( $str );

                $this->_parseSpace( $str , __LINE__ , __FILE__ );
                $this->_getNextToken( $str , $hierarchydelim );

                $this->_parseSpace( $str,__LINE__ , __FILE__);
                $this->_getNextToken( $str , $mailbox_name );

                $result_array = array( "NAME_ATTRIBUTES"=>$params_arr , "HIERACHY_DELIMITER"=>$hierarchydelim , "MAILBOX_NAME"=> $this->utf_7_decode($mailbox_name) );
                return array($token=>$result_array);
            break;

            case "SEARCH" :
                $str_line = rtrim( substr( $this->_getToEOL( $str , false ) , 1) );
                $struct_arr["SEARCH_LIST"] = explode( ' ' , $str_line );
                if(count($struct_arr["SEARCH_LIST"]) == 1 && $struct_arr["SEARCH_LIST"][0]==''){
                    $struct_arr["SEARCH_LIST"]=null;
                }
                return array($token=>$struct_arr);
            break;
            case "OK" :
                /* TODO:
                    parse the [ .... ] part of the response, use the method
                    _getEXTarray(&$str,'[',$stopDelim=']')

                */
                $str_line = rtrim( substr( $this->_getToEOL( $str , false ) , 1 ) );
                if($str_line[0] == '[' ){
                    $braceLen=$this->_getClosingBracesPos($str_line, '[', ']' );
                    $str_aux='('. substr($str_line,1,$braceLen -1). ')';
                    $ext_arr=$this->_getEXTarray($str_aux);
                    //$ext_arr=array($token=>$this->_getEXTarray($str_aux));
                }else{
                    $ext_arr=$str_line;
                    //$ext_arr=array($token=>$str_line);
                }
                $result_array =  $ext_arr;
                return $result_array;
                break;
        case "NO" :
        /* TODO:
            parse the [ .... ] part of the response, use the method
            _getEXTarray(&$str,'[',$stopDelim=']')

        */

            $str_line = rtrim( substr( $this->_getToEOL( $str , false ) , 1 ) );
            $result_array[] = @array( "COMMAND"=>$token , "EXT"=>$str_line );
            return $result_array;
            break;
        case "BAD" :
        /* TODO:
            parse the [ .... ] part of the response, use the method
            _getEXTarray(&$str,'[',$stopDelim=']')

        */

            $str_line = rtrim( substr( $this->_getToEOL( $str , false ) , 1 ) );
            $result_array[] = array( "COMMAND"=>$token , "EXT"=>$str_line );
            return $result_array;
            break;
        case "BYE" :
        /* TODO:
            parse the [ .... ] part of the response, use the method
            _getEXTarray(&$str,'[',$stopDelim=']')

        */

            $str_line = rtrim( substr( $this->_getToEOL( $str , false ) , 1 ) );
            /* EmailReporting: Changed $command to $token as it causes and error on logout */
            $result_array[] = array( "COMMAND"=>$token , "EXT"=> $str_line );
            return $result_array;
            break;

        case "LISTRIGHTS" :
                $this->_parseSpace( $str ,__LINE__ , __FILE__ );
                $this->_getNextToken( $str , $mailbox );
                $this->_parseSpace( $str , __LINE__ , __FILE__ );
                $this->_getNextToken( $str , $user );
                $this->_parseSpace( $str , __LINE__ , __FILE__ );
                $this->_getNextToken( $str , $granted );

                $ungranted = explode( ' ' , rtrim( substr( $this->_getToEOL( $str , false ) , 1 ) ) );

                $result_array = @array( "MAILBOX"=>$this->utf_7_decode($mailbox) , "USER"=>$user , "GRANTED"=>$granted , "UNGRANTED"=>$ungranted );
                return $result_array;
            break;

        case "MYRIGHTS" :
                $this->_parseSpace( $str , __LINE__ , __FILE__ );
                $this->_getNextToken( $str ,$mailbox );
                $this->_parseSpace( $str , __LINE__ , __FILE__ );
                $this->_getNextToken( $str , $granted );

                $result_array = array( "MAILBOX"=>$this->utf_7_decode($mailbox) , "GRANTED"=>$granted );
                return $result_array;
            break;

        case "ACL" :
                $this->_parseSpace( $str , __LINE__ , __FILE__ );
                $this->_getNextToken( $str , $mailbox );
                $this->_parseSpace( $str , __LINE__ , __FILE__ );
                $acl_arr = explode( ' ' , rtrim( substr( $this->_getToEOL( $str , false ) , 0 ) ) );

                for( $i = 0 ; $i < count( $acl_arr ) ; $i += 2 ){
                    $arr[] = array( "USER"=>$acl_arr[$i] , "RIGHTS"=>$acl_arr[ $i + 1 ] );
                }

                $result_array = array( "MAILBOX"=>$this->utf_7_decode($mailbox) , "USERS"=>$arr );
                return $result_array;
            break;

        case "ANNOTATION" :
                $this->_parseSpace($str, __LINE__, __FILE__);
                $this->_getNextToken($str, $mailbox);

                $this->_parseSpace($str, __LINE__, __FILE__);
                $this->_getNextToken($str, $entry);

                $this->_parseSpace($str, __LINE__, __FILE__);
                $attrs = $this->_arrayfy_content($str);

                $result_array = array('MAILBOX' => $mailbox, 'ENTRY' => $entry , 'ATTRIBUTES' => $attrs);
                return $result_array;
            break;

        case "":
            $this->_prot_error( "PROTOCOL ERROR!:str empty!!" , __LINE__ , __FILE__ );
            break;
        case "(":
            $this->_prot_error("OPENING PARENTHESIS ERROR!!!!!!!!!!!!!!!!!" , __LINE__ , __FILE__ );
            break;
        case ")":
            //"CLOSING PARENTHESIS BREAK!!!!!!!"
            break;
        case "\r\n":
            $this->_prot_error("BREAK!!!!!!!!!!!!!!!!!" , __LINE__ , __FILE__ );
            break;
        case ' ':
            // this can happen and we just ignore it
            // This happens when - for example - fetch returns more than 1 parammeter
            // for example you ask to get RFC822.SIZE and UID
            //$this->_prot_error("SPACE BREAK!!!!!!!!!!!!!!!!!" , __LINE__ , __FILE__ );
            break;
        default:
            $body_token=strtoupper(substr($token,0,5));
            //echo "BODYYYYYYY: $body_token\n";
            $rfc822_token=strtoupper(substr($token,0,7));
            //echo "BODYYYYYYY: $rfc822_token|$token\n";

            if( $body_token == 'BODY[' || $body_token == 'BODY.' || $rfc822_token == 'RFC822.' ) {
                //echo "TOKEN:$token\n";
                //$this->_getNextToken( $str , $mailbox );
                return array($token=>$this->_parseContentresponse( $str , $token ));
            }else{
                $this->_prot_error( "UNIMPLEMMENTED! I don't know the parameter '$token' !!!" , __LINE__ , __FILE__ );
            }
            break;
        }
        return false;
}






    /*
    * Verifies that the next character IS a space
    */
    function _parseSpace(&$str,$line,$file, $printError = true)
    {
    /*
        This code repeats a lot in this class
        so i make it a function to make all the code shorter
    */
        $this->_getNextToken( $str , $space );
        if( $space != ' ' ){
            $this->_prot_error("must be a ' ' but is a '$space' !!!!" , $line , $file,$printError );
        }
        return $space;
    }






    function _parseString( &$str , $char , $line , $file )
    {
    /*
        This code repeats a lot in this class
        so i make it a function to make all the code shorter
    */
        $this->_getNextToken( $str , $char_aux );
        if( strtoupper($char_aux) != strtoupper( $char ) ){
            $this->_prot_error("must be a $char but is a '$char_aux' !!!!", $line , $file );
        }
        return $char_aux;
    }





    function _genericImapResponseParser( &$str , $cmdid = null )
    {

        $result_array=array();
        if( $this->_unParsedReturn ){
            $unparsed_str = $str;
        }

        $this->_getNextToken( $str , $token );

        while( $token != $cmdid && $str != '' ){
        if($token == "+" ){
        //if the token  is + ignore the line
        // TODO: verify that this is correct!!!
            $this->_getToEOL( $str );
            $this->_getNextToken( $str , $token );
        }

            $this->_parseString( $str , ' ' , __LINE__ , __FILE__ );

            $this->_getNextToken( $str , $token );
        if( $token == '+' ){
            $this->_getToEOL( $str );
            $this->_getNextToken( $str , $token );
        }else
            if( is_numeric( $token ) ){
                // The token is a NUMBER so I store it
                $msg_nro = $token;
                $this->_parseSpace( $str , __LINE__ , __FILE__ );

                // I get the command
                $this->_getNextToken( $str , $command );

                if( ( $ext_arr = $this->_retrParsedResponse( $str , $command, $msg_nro ) ) == false ){
                //  if this bogus response cis a FLAGS () or EXPUNGE response
                // the ignore it
                    if( $command != 'FLAGS' && $command != 'EXPUNGE' ){
                        $this->_prot_error("bogus response!!!!" , __LINE__ , __FILE__, false);
                    }
                }
                $result_array[] = array( "COMMAND"=>$command , "NRO"=>$msg_nro , "EXT"=>$ext_arr );
            }else{
                // OK the token is not a NUMBER so it MUST be a COMMAND
                $command = $token;

                /* Call the parser return the array
                    take care of bogus responses!
                */

                if( ( $ext_arr = $this->_retrParsedResponse( $str , $command ) ) == false ){
                    $this->_prot_error( "bogus response!!!! (COMMAND:$command)" , __LINE__ , __FILE__ );
                }
                $result_array[] = array( "COMMAND"=>$command , "EXT"=>$ext_arr );


            }


            $this->_getNextToken( $str , $token );

            $token = strtoupper( $token );
            if( $token != "\r\n" && $token != '' ){
                $this->_prot_error("PARSE ERROR!!! must be a '\\r\\n' here  but is a '$token'!!!! (getting the next line)|STR:|$str|" , __LINE__ , __FILE__ );
            }
            $this->_getNextToken( $str , $token );

            if($token == "+" ){
                //if the token  is + ignore the line
                // TODO: verify that this is correct!!!
                $this->_getToEOL( $str );
                $this->_getNextToken( $str , $token );
            }
        }//While
        // OK we finish the UNTAGGED Response now we must parse the FINAL TAGGED RESPONSE
        //TODO: make this a litle more elegant!

        $this->_parseSpace( $str , __LINE__ , __FILE__, false );

        $this->_getNextToken( $str , $cmd_status );

        $str_line = rtrim (substr( $this->_getToEOL( $str ) , 1 ) );


        $response["RESPONSE"]=array( "CODE"=>$cmd_status , "STR_CODE"=>$str_line , "CMDID"=>$cmdid );

        $ret=$response;
        if( !empty($result_array)){
            $ret=array_merge($ret,array("PARSED"=>$result_array) );
        }

        if( $this->_unParsedReturn ){
            $unparsed["UNPARSED"]=$unparsed_str;
            $ret=array_merge($ret,$unparsed);
        }


        if( isset($status_arr) ){
            $status["STATUS"]=$status_arr;
            $ret=array_merge($ret,$status);
        }
        return $ret;

}




    function _genericCommand($command, $params = '')
    {
        if( !$this->_connected ){
            return new PEAR_Error( "not connected! (CMD:$command)" );
        }
        $cmdid = $this->_getCmdId();
        $this->_putCMD( $cmdid , $command , $params );
        $args=$this->_getRawResponse( $cmdid );
        return $this->_genericImapResponseParser( $args , $cmdid );
    }



     function utf_7_encode($str)
    {
        if($this->_useUTF_7 == false ){
            return $str;
        }
        //return imap_utf7_encode($str);

        $encoded_utf7 = '';
        $base64_part  = '';
    if(is_array($str)){
        return new PEAR_Error('error');
    }


        for ($i = 0; $i < strlen($str); $i++) {
            //those chars should be base64 encoded
            if ( ((ord($str[$i]) >= 39 ) and (ord($str[$i]) <= 126 )) or ((ord($str[$i]) >= 32 ) and (ord($str[$i]) <= 37 )) ) {
                if ($base64_part) {
                    $encoded_utf7 = sprintf("%s&%s-", $encoded_utf7, str_replace('=', '',base64_encode($base64_part))  );
                    $base64_part = '';
                }
                $encoded_utf7 = sprintf("%s%s",$encoded_utf7 , $str[$i]);
            } else {
                //handle &
                if (ord($str[$i]) == 38 ) {
                    if ($base64_part) {
                        $encoded_utf7 = sprintf("%s&%s-", $encoded_utf7, str_replace('=', '',base64_encode($base64_part))  );
                        $base64_part = '';
                    }
                    $encoded_utf7 = sprintf("%s&-", $encoded_utf7 );
                } else {
                    $base64_part = sprintf("%s%s",$base64_part  , $str[$i]);
                    //$base64_part = sprintf("%s%s%s",$base64_part , chr(0) , $str[$i]);
                }
            }
        }
        if ($base64_part) {
            $encoded_utf7 = sprintf("%s&%s-", $encoded_utf7, str_replace('=', '',base64_encode($base64_part))   );
            $base64_part = '';
        }
        return $encoded_utf7;
    }


    function utf_7_decode($str)
    {

        if($this->_useUTF_7 == false ){
            return $str;
        }

        //return imap_utf7_decode($str);

        $base64_part = '';
        $decoded_utf7 = '';

        for ($i = 0; $i < strlen($str); $i++) {
            if ( strlen($base64_part) > 0 ) {
                if ($str[$i] == '-') {
                    if ($base64_part == '&') {
                        $decoded_utf7 = sprintf("%s&" , $decoded_utf7 );
                    } else {
                        $next_part_decoded= base64_decode( substr( $base64_part, 1 ) ) ;
                        $decoded_utf7 = sprintf("%s%s", $decoded_utf7 , $next_part_decoded );
                    }
                    $base64_part = '';

                } else {
                    $base64_part = sprintf("%s%s", $base64_part , $str[$i] );
                }
            } else {
                if ($str[$i] == '&') {
                    $base64_part = '&';
                } else {
                    $decoded_utf7 = sprintf("%s%s", $decoded_utf7 , $str[$i] );
                }
            }
        }
        return $decoded_utf7;
    }



}//Class
?>
