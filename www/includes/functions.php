<?php

	function sexec($command, &$output = NULL, &$return = NULL)
	{
		return exec('/home/inkspot/bin/suexec ' . $command, $output, $return);
	}
