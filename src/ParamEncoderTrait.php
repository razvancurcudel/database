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
 * Manages param encoders and provides a way to encode specific values.
 * 
 * @author Martin Schröder
 */
trait ParamEncoderTrait
{
	protected $paramEncoders = [];
	
	public function getParamEncoders()
	{
		return $this->paramEncoders;
	}
	
	public function encodeParam($value, $type = NULL)
	{
		$done = false;
	
		foreach($this->paramEncoders as $encoder)
		{
			$result = $encoder->encodeParam($this, $value, $done);
	
			if($done)
			{
				return $result;
			}
		}
	
		return $value;
	}
	
	public function registerParamEncoder(ParamEncoderInterface $encoder)
	{
		if(!in_array($encoder, $this->paramEncoders, true))
		{
			$this->paramEncoders[] = $encoder;
		}
	}
	
	public function unregisterParamEncoder(ParamEncoderInterface $encoder)
	{
		if(false !== ($index = array_search($encoder, $this->paramEncoders, true)))
		{
			unset($this->paramEncoders[$index]);
		}
	}
}
