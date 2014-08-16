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
 * Decorates a database connection with param encoders used by prepared statements.
 * 
 * @author Martin Schröder
 */
class ParamEncoderDecorator extends ConnectionDecorator
{
	protected $encoders = [];
	
	/**
	 * {@inheritdoc}
	 */
	public function prepare($sql)
	{
		$stmt = $this->conn->prepare($sql);
		
		foreach($this->encoders as $encoder)
		{
			$stmt->registerParamEncoder($encoder);
		}
		
		return $stmt;
	}
		
	public function registerParamEncoder(ParamEncoderInterface $encoder)
	{
		if(!in_array($encoder, $this->encoders, true))
		{
			$this->encoders[] = $encoder;
		}
		
		return $this;
	}
	
	public function unregisterParamEncoder(ParamEncoderInterface $encoder)
	{
		if(false !== ($index = array_search($encoder, $this->encoders, true)))
		{
			unset($this->encoders[$index]);
		}
		
		return $this;
	}
}
