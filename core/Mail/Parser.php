<?php

require_once( 'Mail/mimeDecode.php' );
require_once( plugin_config_get( 'path_erp', NULL, TRUE ) . 'core/Mail/simple_html_dom.php');

class ERP_Mail_Parser
{
	private $_parse_html = FALSE;
	private $_parse_mime = FALSE;
	private $_encoding = 'UTF-8';
	private $_add_attachments = TRUE;

	private $_file;
	private $_content;

	private $_from;
	private $_subject;
	private $_charset = 'auto';
	private $_priority;
	private $_transferencoding;
	private $_body;
	private $_parts = array();
	private $_ctype = array();

	private $_mb_list_encodings = array();

	public function __construct( $options )
	{
		$this->_parse_mime = $options[ 'parse_mime' ];
		$this->_parse_html = $options[ 'parse_html' ];
		$this->_encoding = $options[ 'encoding' ];
		$this->_add_attachments = $options[ 'add_attachments' ];

		$this->prepare_mb_list_encodings();
	}
	
	private function prepare_mb_list_encodings()
	{
		if ( extension_loaded( 'mbstring' ) )
		{
			$t_charset_list = mb_list_encodings();

			$r_charset_list = array();
			foreach ( $t_charset_list AS $value )
			{
				$r_charset_list[ $value ] = strtolower( $value );
			}

			$this->_mb_list_encodings = $r_charset_list;
		}
	}

	public function setInputString( $content )
	{
		$this->_file = NULL;
		$this->_content = $content;
	}

	public function setInputFile( $file )
	{
		$this->_file = $file;
		$this->_content = file_get_contents( $this->_file );
	}

	private function process_encoding( $encode )
	{
		if ( extension_loaded( 'mbstring' ) && $this->_encoding !== $this->_charset )
		{
			$encode = mb_convert_encoding( $encode, $this->_encoding, $this->_charset );
		}

		return( $encode );
	}

	public function parse()
	{
		$decoder = new Mail_mimeDecode( $this->_content );
		$this->_content = NULL;

		$params['include_bodies'] = TRUE;
		$params['decode_bodies'] = TRUE;
		$params['decode_headers'] = TRUE;
		$params['rfc_822bodies'] = TRUE;

		$structure = $decoder->decode( $params );

		$this->parseStructure( $structure );

		if ( extension_loaded( 'mbstring' ) )
		{
			$this->_from = $this->process_encoding( $this->_from );
			$this->_subject = $this->process_encoding( $this->_subject );
			$this->_body = $this->process_encoding( $this->_body );
		}
	}

	public function from()
	{
		return( $this->_from );
	}

	public function subject()
	{
		return( $this->_subject );
	}

	public function priority()
	{
		return( $this->_priority );
	}

	public function body()
	{
		return( $this->_body );
	}

	public function parts()
	{
		return( $this->_parts );
	}

	private function parseStructure( $structure )
	{
		$this->setFrom( $structure->headers['from'] );
		$this->setSubject( $structure->headers['subject'] );
		$this->setContentType( $structure->ctype_primary, $structure->ctype_secondary );

		if ( isset( $structure->ctype_parameters[ 'charset' ] ) )
		{
			$this->setCharset( $structure->ctype_parameters[ 'charset' ] );
		}

 		if ( isset( $structure->headers['x-priority'] ) )
 		{
			$this->setPriority( $structure->headers['x-priority'] );
		}

		if ( isset( $structure->headers['content-transfer-encoding'] ) )
		{
			$this->setTransferEncoding( $structure->headers['content-transfer-encoding'] );
		}

		if ( isset( $structure->body ) )
		{
			$this->setBody( $structure->body );
		}

		if ( $this->_parse_mime && isset( $structure->parts ) )
		{
			$this->setParts( $structure->parts );
		}
	}

	private function setFrom( $from )
	{
		$this->_from = $from;
	}

	private function setSubject( $subject )
	{
		$this->_subject = $subject;
	}

	private function setCharset( $charset )
	{
		if ( extension_loaded( 'mbstring' ) && $this->_charset === 'auto' )
		{
			$t_arraysearch_result = array_search( strtolower( $charset ), $this->_mb_list_encodings, TRUE );
			$this->_charset = ( ( $t_arraysearch_result !== FALSE ) ? $t_arraysearch_result : 'auto' );
		}
	}

	private function setPriority( $priority )
	{
		$this->_priority = $priority;
	}

	private function setTransferEncoding( $transferencoding )
	{
		$this->_transferencoding = $transferencoding;
	}
	
	private function setContentType( $primary, $secondary )
	{
		$this->_ctype['primary'] = $primary;
		$this->_ctype['secondary'] = $secondary;
	}

	private function setBody( $body )
	{
		if ( is_blank( $body ) || !is_blank( $this->_body ) )
		{
			return;
		}

		if ( 'text' === $this->_ctype['primary'] &&	'plain' === $this->_ctype['secondary'] )
		{
			$this->_body = $body;
		}
		elseif ( $this->_parse_html && 'text' === $this->_ctype['primary'] && 'html' === $this->_ctype['secondary'] )
		{
			$htmlToText = str_get_html( $body );

			// extract text from HTML
			$this->_body = $htmlToText->plaintext;
		}
	}

	private function setParts( &$parts, $attachment = FALSE, $p_attached_email_subject = NULL )
	{
		$i = 0;

		if ( $attachment === TRUE && $p_attached_email_subject === NULL && !empty( $parts[ $i ]->headers[ 'subject' ] ) )
		{
			$p_attached_email_subject = $parts[ $i ]->headers[ 'subject' ];
		}

		if ( 'text' === $parts[ $i ]->ctype_primary && in_array( $parts[ $i ]->ctype_secondary, array( 'plain', 'html' ) ) )
		{
			$t_stop_part = FALSE;

			// Let's select the plaintext body if we can find it
			// It must only have 2 parts. Most likely one is text/html and one is text/plain
			if (
				count( $parts ) === 2 && !isset( $parts[ $i ]->parts ) && !isset( $parts[ $i+1 ]->parts ) &&
				'text' === $parts[ $i+1 ]->ctype_primary &&
				in_array( $parts[ $i+1 ]->ctype_secondary, array( 'plain', 'html' ) ) && 
				$parts[ $i ]->ctype_secondary !== $parts[ $i+1 ]->ctype_secondary
			)
			{
				if ( $parts[ $i ]->ctype_secondary !== 'plain' )
				{
					$i++;
				}

				$t_stop_part = TRUE;
			}

			$this->setContentType( $parts[$i]->ctype_primary, $parts[ $i ]->ctype_secondary );

			if ( isset( $parts[ $i ]->headers[ 'content-transfer-encoding' ] ) )
			{
				$this->setTransferEncoding( $parts[ $i ]->headers[ 'content-transfer-encoding' ] );
			}

			if ( isset( $parts[$i]->ctype_parameters[ 'charset' ] ) )
			{
				$this->setCharset( $parts[$i]->ctype_parameters[ 'charset' ] );
			}

			if ( $attachment === TRUE )
			{
				$this->addPart( $parts[ $i ], $p_attached_email_subject );
			}
			else
			{
				$this->setBody( $parts[ $i ]->body );
			}

			if ( $t_stop_part === TRUE )
			{
				return;
			}

			$i++;
		}

		for ( $i; $i < count( $parts ); $i++ )
		{
			if ( 'multipart' == $parts[ $i ]->ctype_primary )
			{
				$this->setParts( $parts[ $i ]->parts, $attachment, $p_attached_email_subject );
			}
			elseif ( 'message' == $parts[ $i ]->ctype_primary && $parts[ $i ]->ctype_secondary === 'rfc822' )
			{
				$this->setParts( $parts[ $i ]->parts, TRUE );
			}
			else
			{
				$this->addPart( $parts[ $i ] );
			}
		}
	}
	
	private function addPart( &$part, $p_alternative_name = NULL )
	{
		if ( $this->_add_attachments )
		{
			$p[ 'ctype' ] = $part->ctype_primary . "/" . $part->ctype_secondary;

			if ( isset( $part->ctype_parameters[ 'name' ] ) ) {
				$p[ 'name' ] = $part->ctype_parameters[ 'name' ];
			}
			elseif ( isset( $part->headers[ 'content-disposition' ] ) && strpos( $part->headers[ 'content-disposition' ], 'filename="' ) !== FALSE )
			{
				$p[ 'name' ] = custom_substr( $part->headers[ 'content-disposition' ], 'filename="', '"' );
			}
			elseif ( isset( $part->headers[ 'content-type' ] ) && strpos( $part->headers[ 'content-type' ], 'name="' ) !== FALSE )
			{
				$p[ 'name' ] = custom_substr( $part->headers[ 'content-type' ], 'name="', '"' );
			}
			elseif ( 'text' == $part->ctype_primary && in_array( $part->ctype_secondary, array( 'plain', 'html' ) ) && !empty( $p_alternative_name ) )
			{
				$p[ 'name' ] = $p_alternative_name . ( ( $part->ctype_secondary === 'plain' ) ? '.txt' : '.html' );
			}

			$p[ 'body' ] = $part->body;

			if ( extension_loaded( 'mbstring' ) && !empty( $p[ 'name' ] ) )
			{
				if ( isset( $part->ctype_parameters[ 'charset' ] ) )
				{
					$this->setCharset( $part->ctype_parameters[ 'charset' ] );
				}

				$p[ 'name' ] = $this->process_encoding( $p[ 'name' ] );
			}

			$this->_parts[] = $p;
		}
	}

	private function custom_substr( $p_string, $p_string_start, $p_string_end )
	{
		$t_start = strpos( $p_string, $p_string_start ) + strlen( $p_string_start );
		$t_end = strpos( $p_string, $p_string_end, $t_start );
		$t_result = substr( $p_string, $t_start, ( $t_end - $t_start ) );

		return( $t_result );
	}
}

?>
