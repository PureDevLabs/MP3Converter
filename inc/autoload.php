<?php
	// Autoload class files
	spl_autoload_register(function($className) {
		if (!class_exists($className))
		{
			// Project-specific namespace prefix
			$prefix = 'MP3Converter\\';

			// Base directory for the namespace prefix
			$baseDir = dirname(__DIR__) . DIRECTORY_SEPARATOR;

			// Does the class use the namespace prefix? If not, move to the next registered autoloader.
			$len = strlen($prefix);
			if (strncmp($prefix, $className, $len) !== 0) return;

			// Get the relative class name
			$relativeClass = substr($className, $len);

			// Prepend with base directory, replace namespace separators with directory separators in the relative class name,
			// then append with .php
			$file = $baseDir . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';

			// If file exists, include it.
			if (file_exists($file))
			{
				include $file;
			}
		}
	});
?>