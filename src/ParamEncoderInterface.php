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
 * Contract for a custom param encoder to be applied to bound input params.
 * 
 * @author Martin Schröder
 */
interface ParamEncoderInterface
{
	/**
	 * Encode and return the given param to be used as input param in the given connection.
	 * 
	 * @param Connection $conn The connection being used to execute a statement.
	 * @param mixed $param Param value to be encoded.
	 * @param boolean $isEncoded Set to true when the param has been encoded.
	 * @return mixed The encoded value.
	 */
	public function encodeParam(Connection $conn, $param, & $isEncoded);
}
