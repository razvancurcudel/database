<?php

/*
 * This file is part of KoolKode Database.
 *
 * (c) Martin Schröder <m.schroeder2007@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KoolKode\Database;

/**
 * Param encoder that delegates work to a callback.
 * 
 * @author Martin Schröder
 */
class CallbackParamEncoder implements ParamEncoderInterface
{
	protected $callback;
	
	public function __construct(callable $callback)
	{
		$this->callback = $callback;
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function encodeParam(ConnectionInterface $conn, $param, & $isEncoded)
	{
		return call_user_func($this->callback, $conn, $param, $isEncoded);
	}
}
