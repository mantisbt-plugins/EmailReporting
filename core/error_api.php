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

?>
