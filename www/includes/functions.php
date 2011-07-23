<?php

	function sexec($command, &$output = NULL, &$return = NULL)
	{
		return exec('sudo ' . $command, $output, $return);
	}
