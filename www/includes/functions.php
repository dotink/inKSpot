<?php

	function sexec($command, &$output = NULL, &$return = NULL)
	{
		return exec('/home/inkspot/sbin/' . $command, $output, $return);
	}
