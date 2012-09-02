<?php

namespace Dws\Console;

/**
 * A PHP getopt wrapper that neatly handles long/short options
 *
 * @author David Weinraub <david.weinraub@diamondwebservices.com>
 */
class GetOpt
{
	/**
	 * $params = array(
	 *	array(
	 *		'short' => 'v',
	 *		'long'	=> 'verbose'
	 *		'description' => 'Provide verbose output'
	 *		'allowsValue'	=> false,
	 *		'requiresValue'	=> false,
	 *		'isRequired'	=> true,
	 *  ),
	 *	array(
	 *		'short' => 'f',
	 *		'long'	=> 'file'
	 *		'description' => 'The file to putput'
	 *		'allowsValue'		=> true,
	 *		'requiresValue'		=> true,
	 *		'isRequired'		=> true,
	 *  ),
	 * );
	 * @param array $params
	 */
    public function __construct($params)
    {
		$this->params = $params;
		$this->init();
    }

	protected function init()
	{
		$short = '';
		$long = '';
		$numParams = count($this->params);
		for ($i = 0; $i < $numParams; $i++) {
					
			if ($this->params[$i]['requiresValue']){
				$this->params[$i]['allowsValue'] = true;
			}
			if (!$this->params[$i]['allowsValue']){
				$this->params['requiresValue'] = false;
			}
			
			// build up the short param string
			$short .= $param['short'];
			if ($param['allowsValue']){
				$short .= ':';
				if ($params)
			}
			
			
			$long = $param['long'];
		}
		$this->options = getopt($short, $long);
		
	}
	
	protected function createShortParams()
	{
	}
	
	public function isValid()
	{
		
	}
	
	public function getUsage()
	{
		
	}
	
	public function getOption($option)
	{
		
	}
	
	public function __get($name)
	{
		return $this->getOption($name);
	}
}
