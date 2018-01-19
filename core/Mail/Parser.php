<?php

//require_once( 'Mail/mimeDecode.php' );
plugin_require_api( 'core_pear/Mail/mimeDecode.php' );
plugin_require_api( 'core/Mail/simple_html_dom.php');
plugin_require_api( 'core/Mail/Markdownify/Converter.php');
plugin_require_api( 'core/Mail/Markdownify/ConverterExtra.php');
plugin_require_api( 'core/Mail/Markdownify/Parser.php');

class ERP_Mail_Parser
{
	private $_parse_html = FALSE;
	private $_parse2markdown = FALSE;
	private $_encoding = 'UTF-8';
	private $_add_attachments = TRUE;
	private $_debug = FALSE;
	private $_show_mem_usage = FALSE;
	private $_memory_limit = FALSE;
	private $_mailbox_starttime = NULL;

	private $_file;
	private $_content;

	private $_from;
	private $_to;
	private $_cc;
	private $_subject;
	private $_def_charset = 'auto';
	private $_fallback_charset = 'ASCII';
	private $_priority;
	private $_messageid;
	private $_references = array();
	private $_inreplyto;
	private $_body;
	private $_parts = array();
	private $_ctype = array();

	private $_mb_list_encodings = array();

	/**
	* Based on horde-3.3.13 function _mbstringCharset
	*
	* Workaround charsets that don't work with mbstring functions.
	*
	* The keys in this array should be lowercase
	*
	* @access private
	*
	* mbstring functions do not handle the 'ks_c_5601-1987' &
	* 'ks_c_5601-1989' charsets. However, these charsets are used, for
	* example, by various versions of Outlook to send Korean characters.
	* Use UHC (CP949) encoding instead. See, e.g.,
	* http://lists.w3.org/Archives/Public/ietf-charsets/2001AprJun/0030.html */
	private $_mbstring_unsupportedcharsets = array(
			'ks_c_5601-1987' => 'UHC',
			'ks_c_5601-1989' => 'UHC',
			'us-ascii' => 'ASCII',
	);

	public function __construct( $options, $mailbox_starttime = NULL )
	{
		$this->_parse_html = $options[ 'parse_html' ];
		if ( $this->_parse_html )
		{
			$this->_parse2markdown = $options[ 'parse2markdown' ];
		}
		$this->_add_attachments = $options[ 'add_attachments' ];
		$this->_debug = $options[ 'debug' ];
		$this->_show_mem_usage = $options[ 'show_mem_usage' ];
		$this->_mailbox_starttime = $mailbox_starttime;

		$this->prepare_mb_list_encodings();

		if ( $this->_debug )
		{
			$this->_memory_limit = ini_get( 'memory_limit' );
		}
	}

	private function prepare_mb_list_encodings()
	{
		if ( extension_loaded( 'mbstring' ) )
		{
			$this->_encoding = mb_internal_encoding();

			$t_charset_list = mb_list_encodings();

			// This function does not exist in version older then PHP 5.3.0
			if ( function_exists( 'mb_encoding_aliases' ) )
			{
				$t_encoding_aliases = array();
				foreach ( $t_charset_list AS $t_value )
				{
					$t_encoding_aliases = array_merge( $t_encoding_aliases, mb_encoding_aliases( $t_value ) );
				}

				$t_charset_list = array_merge( $t_charset_list, $t_encoding_aliases );
			}

			$r_charset_list = array();
			foreach ( $t_charset_list AS $t_value )
			{
				$r_charset_list[ strtolower( $t_value ) ] = $t_value;
			}

			$this->_mb_list_encodings = $r_charset_list + $this->_mbstring_unsupportedcharsets;
		}
	}

	public function setInputString( &$content )
	{
		$this->_file = NULL;
		$this->_content = $content;
	}

	public function setInputFile( $file )
	{
		$this->_file = $file;
		$this->_content = file_get_contents( $this->_file );
	}

	private function process_body_encoding( $encode, $charset )
	{
		if ( extension_loaded( 'mbstring' ) )
		{
			if ( $charset === NULL || $charset === 'auto' || !isset( $this->_mb_list_encodings[ strtolower( $charset ) ] ) )
			{
				$charset = mb_detect_encoding( $encode, $this->_def_charset );
			}

			if ( $charset === FALSE )
			{
				$charset = $this->_fallback_charset;
				echo "\n" . 'Message: Charset detection failed on: ' . $encode . "\n";
			}

			if ( $this->_encoding !== $charset )
			{
				$t_encode = mb_convert_encoding( $encode, $this->_encoding, $this->_mb_list_encodings[ strtolower( $charset ) ] );

				if ( $t_encode !== FALSE )
				{
					return( $t_encode );
				}
			}

			// Remove any invisible unicode control format characters
			// https://www.fileformat.info/info/unicode/category/Cf/index.htm
			$encode = preg_replace( '/\p{Cf}+/u', "", $encode );
		}

		return( $encode );
	}

	private function process_header_encoding( $encode )
	{
		$use_fallback = FALSE;
		if ( extension_loaded( 'mbstring' ) )
		{
			$t_encode = $encode;
			// Code based on mimedecode function _decodeHeader
			$encoded_words_regex = "/(=\?([^?]+)\?(q|b)\?([^?]*)\?=)/i";
			while ( preg_match( $encoded_words_regex, $t_encode, $matches ) )
			{
				$encoded  = $matches[1];
				$charset  = $matches[2];
				$encoding = $matches[3];
				$text     = $matches[4];

				// Process unsupported fallback charsets
				if ( isset( $this->_mb_list_encodings[ strtolower( $charset ) ] ) && isset( $this->_mbstring_unsupportedcharsets[ strtolower( $charset ) ] ) && $this->_mb_list_encodings[ strtolower( $charset ) ] === $this->_mbstring_unsupportedcharsets[ strtolower( $charset ) ] )
				{
					$charset = $this->_mb_list_encodings[ strtolower( $charset ) ];
				}

				// Process unsupported charsets
				if ( !isset( $this->_mb_list_encodings[ strtolower( $charset ) ] ) )
				{
					echo "\n" . 'Message: Charset not supported: ' . $charset . "\n";
					$charset = $this->_fallback_charset;
				}

				// mb_decode_mimeheader leaves underscores where there should be spaces incase of quoted-printable mimeheaders. Applying workaround.
				if ( strtolower( $encoding ) === 'q' )
				{
					$text = str_replace( '_', ' ', $text );
				}

				$encode_part = mb_decode_mimeheader( '=?' . $charset . '?' . $encoding . '?' . $text . '?=' );

				$t_encode = str_replace( $encoded, $encode_part, $t_encode );
			}

			// If any encoded-words are left then mb_decode_mimeheader did not work as intended. Performing fallback
			if ( preg_match( $encoded_words_regex, $t_encode ) )
			{
				$use_fallback = TRUE;
			}
		}

		if ( !extension_loaded( 'mbstring' ) || $use_fallback === TRUE )
		{
			$decoder = new Mail_mimeDecode( NULL );
			$t_encode = $decoder->_decodeHeader( $encode );

			if ( extension_loaded( 'mbstring' ) && $use_fallback === TRUE )
			{
				// Destroying invalid characters and possibly valid utf8 characters incase of a fallback situation
				$t_encode = $this->process_body_encoding( $t_encode, $this->_fallback_charset );
			}
		}

		return( $t_encode );
	}

	private function decode( &$email )
	{
		$decoder = new Mail_mimeDecode( $email );
		$email = NULL;
		$decoder->_input = NULL;

		$this->show_memory_usage( 'mimeDecode initiated' );

		$params['include_bodies'] = TRUE;
		$params['decode_bodies'] = TRUE;
		$params['decode_headers'] = FALSE;
		$params['rfc_822bodies'] = FALSE;

		$structure = $decoder->decode( $params );

		$this->show_memory_usage( 'mimeDecode finished' );

		return( $structure );
	}

	private function parse_signed_content( &$structure )
	{
		if ( is_object( $structure ) )
		{
			if ( 'multipart' === strtolower( $structure->ctype_primary ) && 'signed' === strtolower( $structure->ctype_secondary ) )
			{
				$structure_signed = $this->decode( $structure->parts['msg_body'] );
				unset( $structure->parts[ 'msg_body' ], $structure->parts[ 'sig_hdr' ], $structure->parts[ 'sig_body' ] );

				$structure = (object) array_replace_recursive( (array) $structure, (array) $structure_signed );
			}
		}
	}

	public function parse()
	{
		$this->show_memory_usage( 'Start parse' );

		$structure = $this->decode( $this->_content );
		$this->_content = NULL;

		$this->parse_signed_content( $structure );

		$this->show_memory_usage( 'Start parse structure' );

		$this->parseStructure( $structure );
	}

	public function from()
	{
		return( $this->_from );
	}

	public function to()
	{
		return( $this->_to );
	}

	public function cc()
	{
		return( $this->_cc );
	}

	public function subject()
	{
		return( $this->_subject );
	}

	public function priority()
	{
		return( $this->_priority );
	}

	public function messageid()
	{
		return( $this->_messageid );
	}

	public function references()
	{
		return( $this->_references );
	}

	public function inreplyto()
	{
		return( $this->_inreplyto );
	}

	public function body()
	{
		return( $this->_body );
	}

	public function parts()
	{
		return( $this->_parts );
	}

	private function parseStructure( &$structure )
	{
		$this->setFrom( $structure->headers[ 'from' ] );
		$this->setSubject( $structure->headers[ 'subject' ] );

		if ( isset( $structure->headers[ 'x-priority' ] ) )
		{
			$this->setPriority( $structure->headers[ 'x-priority' ] );
		}

		if ( isset( $structure->headers[ 'message-id' ] ) )
		{
			$this->setMessageId( $structure->headers[ 'message-id' ] );
		}

		if ( isset( $structure->headers[ 'references' ] ) )
		{
			$this->setReferences( $structure->headers[ 'references' ] );
		}

		if ( isset( $structure->headers[ 'in-reply-to' ] ) )
		{
			$this->setInReplyTo( $structure->headers[ 'in-reply-to' ] );
		}

		if ( isset( $structure->body ) )
		{
			$t_body_charset = NULL;
			if ( isset( $structure->ctype_parameters[ 'charset' ] ) )
			{
				$t_body_charset = $structure->ctype_parameters[ 'charset' ];
			}

			$this->setBody( $structure->body, $structure->ctype_primary, $structure->ctype_secondary, $t_body_charset );
		}

		if ( isset( $structure->parts ) )
		{
			$this->setParts( $structure->parts );
		}

		if ( isset( $structure->headers[ 'to' ] ) )
		{
			$this->setTo( $structure->headers[ 'to' ] );
		}

		if ( isset( $structure->headers[ 'cc' ] ) )
		{
			$this->setCc( $structure->headers[ 'cc' ] );
		}
	}

	private function setFrom( $from )
	{
		$this->_from = $this->process_header_encoding( $from );
	}

	private function setTo( $to )
	{
		$this->_to = $this->process_header_encoding( $to );
	}

	private function setCc( $cc )
	{
		$this->_cc = $this->process_header_encoding( $cc );
	}

	private function setSubject( $subject )
	{
		$this->_subject = $this->process_header_encoding( $subject );
	}

	private function setMessageId( $p_messageid )
	{
		$this->_messageid = trim( $p_messageid );
	}

	private function setReferences( $p_references )
	{
		$t_references = explode(' ', $p_references);

		$references = array();
		foreach( $t_references AS $t_reference )
		{
			$references[] = trim( $t_reference );
		}

		$this->_references = $references;
	}

	private function setInReplyTo( $p_inreplyto )
	{
		$this->_inreplyto = trim( $p_inreplyto );
	}

	private function setPriority( $priority )
	{
		$this->_priority = $priority;
	}

	private function setContentType( $primary, $secondary )
	{
		$this->_ctype['primary'] = strtolower( $primary );
		$this->_ctype['secondary'] = strtolower( $secondary );
	}

	private function setBody( $body, $ctype_primary, $ctype_secondary, $charset )
	{
		if ( is_blank( $body ) || !is_blank( $this->_body ) )
		{
			return;
		}

		$this->setContentType( $ctype_primary, $ctype_secondary );

		$body = $this->process_body_encoding( $body, $charset );

		if ( 'text' === $this->_ctype['primary'] &&	'plain' === $this->_ctype['secondary'] )
		{
			$this->_body = trim( $body );
		}
		elseif ( $this->_parse_html && 'text' === $this->_ctype['primary'] && 'html' === $this->_ctype['secondary'] )
		{
			if ( $this->_parse2markdown )
			{
				$html2markdown = new Markdownify\ConverterExtra();
				$html2markdown->setKeepHTML( FALSE );
				$this->_body = $html2markdown->parseString( $body );
				$this->_body = strip_tags( $this->_body );
				$this->_body = preg_replace( "/[\r\n](\s)*[\r\n](\s)*[\r\n]/", "\n\n", $this->_body );
				$this->_body = trim( $this->_body );
			}
			else
			{
				$htmlToText = str_get_html( $body, true, true, $this->_encoding, false ); 

				// extract text from HTML
				$this->_body = trim( $htmlToText->plaintext );
			}
		}
		else
		{
			return( FALSE );
		}
		
		return( TRUE );
	}

	private function setParts( &$parts, $attachment = FALSE, $p_attached_email_subject = NULL )
	{
		if ( !array_key_exists( 0, $parts ) )
		{
			echo "\n" . 'EmailReporting can\'t handle this mime part. Please report this to the EmailReporting project developers' . "\n" . 'Array key: ' . key( $parts ) . "\n";

			return;
		}
	
		$i = 0;

		if ( $attachment === TRUE && $p_attached_email_subject === NULL && !empty( $parts[ $i ]->headers[ 'subject' ] ) )
		{
			$p_attached_email_subject = $parts[ $i ]->headers[ 'subject' ];
		}

		if ( 'text' === strtolower( $parts[ $i ]->ctype_primary ) && in_array( strtolower( $parts[ $i ]->ctype_secondary ), array( 'plain', 'html' ), TRUE ) )
		{
			$t_stop_part = FALSE;

			// We prefer the plaintext body if markdown is disabled. We prefer the html body if markdown is enabled
			// It must only have 2 parts. Most likely one is text/html and one is text/plain
			if (
				count( $parts ) === 2 && !isset( $parts[ $i ]->parts ) && !isset( $parts[ $i+1 ]->parts ) &&
				'text' === strtolower( $parts[ $i+1 ]->ctype_primary ) &&
				in_array( strtolower( $parts[ $i+1 ]->ctype_secondary ), array( 'plain', 'html' ), TRUE ) &&
				strtolower( $parts[ $i ]->ctype_secondary ) !== strtolower( $parts[ $i+1 ]->ctype_secondary )
			)
			{
				if ( ( strtolower( $parts[ $i ]->ctype_secondary ) === 'plain' && $this->_parse2markdown ) ||
					( strtolower( $parts[ $i ]->ctype_secondary ) === 'html' && !$this->_parse2markdown ) )
				{
					$i++;
				}

				$t_stop_part = TRUE;
			}

			if ( $attachment === TRUE )
			{
				$this->addPart( $parts[ $i ], $p_attached_email_subject );
			}
			else
			{
				$t_body_charset = NULL;
				if ( isset( $parts[ $i ]->ctype_parameters[ 'charset' ] ) )
				{
					$t_body_charset = $parts[ $i ]->ctype_parameters[ 'charset' ];
				}

				$t_result = $this->setBody( $parts[ $i ]->body, $parts[ $i ]->ctype_primary, $parts[ $i ]->ctype_secondary, $t_body_charset );

				if ( $t_result === FALSE )
				{
					$this->addPart( $parts[ $i ], $p_attached_email_subject );
				}
			}

			if ( $t_stop_part === TRUE )
			{
				return;
			}

			$i++;
		}

		for ( $i; $i < count( $parts ); $i++ )
		{
			if ( 'multipart' === strtolower( $parts[ $i ]->ctype_primary ) )
			{
				if ( 'signed' === strtolower( $parts[ $i ]->ctype_secondary ) )
				{
					$this->parse_signed_content( $parts[ $i ] );
				}

				$this->setParts( $parts[ $i ]->parts, $attachment, $p_attached_email_subject );
			}
			elseif ( $this->_add_attachments && 'message' === strtolower( $parts[ $i ]->ctype_primary ) && 'rfc822' === strtolower( $parts[ $i ]->ctype_secondary ) )
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
				$p[ 'name' ] = $this->custom_substr( $part->headers[ 'content-disposition' ], 'filename="', '"' );
			}
			elseif ( isset( $part->headers[ 'content-type' ] ) && strpos( $part->headers[ 'content-type' ], 'name="' ) !== FALSE )
			{
				$p[ 'name' ] = $this->custom_substr( $part->headers[ 'content-type' ], 'name="', '"' );
			}
			elseif ( 'text' == strtolower( $part->ctype_primary ) && in_array( strtolower( $part->ctype_secondary ), array( 'plain', 'html' ), TRUE ) && !empty( $p_alternative_name ) )
			{
				$p[ 'name' ] = $p_alternative_name . ( ( strtolower( $part->ctype_secondary ) === 'plain' ) ? '.txt' : '.html' );
			}

			$p[ 'body' ] = ( ( isset( $part->body ) ) ? $part->body : NULL );

			if ( extension_loaded( 'mbstring' ) && !empty( $p[ 'name' ] ) )
			{
				$p[ 'name' ] = $this->process_header_encoding( $p[ 'name' ] );
			}

			$this->_parts[] = $p;
		}
	}

	private function custom_substr( $p_string, $p_string_start, $p_string_end )
	{
		$t_start = stripos( $p_string, $p_string_start ) + strlen( $p_string_start );
		$t_end = stripos( $p_string, $p_string_end, $t_start );
		$t_result = substr( $p_string, $t_start, ( $t_end - $t_start ) );

		return( $t_result );
	}

	# --------------------
	# Show memory usage in debug mode
	private function show_memory_usage( $p_location )
	{
		if ( $this->_debug && $this->_show_mem_usage )
		{
			$t_current_runtime = ( ( $this->_mailbox_starttime !== NULL ) ? round( ERP_get_timestamp() - $this->_mailbox_starttime, 4 ) : 0 );
			echo 'Debug output memory usage' . "\n" .
				'Location: Mail Parser - ' . $p_location . "\n" .
				'Runtime in seconds: ' . $t_current_runtime . "\n" .
				'Current memory usage: ' . ERP_formatbytes( memory_get_usage( FALSE ) ) . ' / ' . $this->_memory_limit . "\n" .
				'Peak memory usage: ' . ERP_formatbytes( memory_get_peak_usage( FALSE ) ) . ' / ' . $this->_memory_limit . "\n" .
				'Current real memory usage: ' . ERP_formatbytes( memory_get_usage( TRUE ) ) . ' / ' . $this->_memory_limit . "\n" .
				'Peak real memory usage: ' . ERP_formatbytes( memory_get_peak_usage( TRUE ) ) . ' / ' . $this->_memory_limit . "\n\n";
		}
	}
}

?>
