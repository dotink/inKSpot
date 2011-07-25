<?php

	function sexec($command, &$output = NULL)
	{
		exec('sudo ' . $command, $output, $return);
		return $return;
	}
