<?php

	function sexec($command, &$output, &$return)
	{
		return exec('/home/inkspot/sbin/' . $command, $output, $return);
	}
