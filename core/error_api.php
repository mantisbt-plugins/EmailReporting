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

abstract class ERP_ErrorHandling
{
	private array $_error = array();

	# --------------------
	# Check whether there are stored errors
	public function hasError(): bool
	{
		return( !empty( $this->_error ) );
	}

	# --------------------
	# Add an error to _error
	protected function setError( string $p_error ): void
	{
		$this->_error[] = $p_error;
	}

	# --------------------
	# Return the first error in _error
	public function getError(): ?string
	{
		return( array_shift( $this->_error ) );
	}

	# --------------------
	# Check whether an operation result contains an PEAR error.
	#
	# When an error is detected it is stored internally.
	# Returns TRUE when an error was detected.
	protected function isPearError( mixed $p_result, string $p_additionalstring = '' ): bool
	{
		if ( PEAR::isError( $p_result ) )
		{
			$this->setError(
				$p_result->getMessage() .
				( ( !empty( $p_result->getCode() ) ) ? ' (' . $p_result->getCode() . ').' : '' ) .
				( ( !empty( $p_additionalstring ) ) ? ' ' . $p_additionalstring : '' )
			);
			return( TRUE );
		}
		else
		{
			return( FALSE );
		}
	}

	# --------------------
	# Handle throwable error.
	#
	# It is stored internally.
	protected function handleThrowable( \Throwable $p_exception, string $p_additionalstring = '' ): void
	{
		$t_error = 
			$p_exception->getMessage() .
			( ( !empty( $p_exception->getCode() ) ) ? ' (' . $p_exception->getCode() . ').' : '' ) .
			( ( !empty( $p_additionalstring ) ) ? ' ' . $p_additionalstring : '' );

		$t_previous = $p_exception->getPrevious();
		if ( $t_previous Instanceof \Throwable )
		{
			$t_error .= "\n" . $t_previous->getMessage() .
				( ( !empty( $t_previous->getCode() ) ) ? ' (' . $t_previous->getCode() . ').' : '' );
		}

		$this->setError( $t_error );
	}

	# --------------------
	# Third-party compatibility helper.
	# Call a function while catching errors as exceptions
	#
	# Third-party code emits PHP warnings instead of
	# proper exceptions. Temporarily convert warnings
	# to exceptions so they can be handled through the
	# normal EmailReporting error handling stack.
	protected function runWithErrorAsException( string $p_command, ?object $p_object = NULL, mixed ...$p_args ): mixed
	{
		$t_result = NULL;

		set_error_handler(
			static function ( $severity, $message, $file, $line )
			{
				throw new \ErrorException( $message, 0, $severity, $file, $line );
			},
			E_ALL
		);

		try
		{
			if ( $p_object === NULL )
			{
				$t_result = $p_command( ...$p_args );
			}
			else
			{
				$t_result = $p_object->$p_command( ...$p_args );
			}
		}
		finally
		{
			restore_error_handler();
		}

		return( $t_result );
	}
}

?>
