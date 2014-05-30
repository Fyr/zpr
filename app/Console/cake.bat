::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::
::
:: Bake is a shell script for running CakePHP bake script
:: PHP 5
::
:: CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
:: Copyright 2005-2012, Cake Software Foundation, Inc.
::
:: Licensed under The MIT License
:: Redistributions of files must retain the above copyright notice.
::
:: @copyright		Copyright 2005-2012, Cake Software Foundation, Inc.
:: @link		http://cakephp.org CakePHP(tm) Project
:: @package   		app.Console
:: @since		CakePHP(tm) v 2.0
:: @license		MIT License (http://www.opensource.org/licenses/mit-license.php)
::
::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::

:: In order for this script to work as intended, the cake\console\ folder must be in your PATH

@echo.
@echo off

SET app=%0
SET lib=%~dp0

e:/OpenServer/modules/php/PHP-5.3.20/php -c e:\OpenServer\userdata\temp\config\php.ini -q "%lib%cake.php" -working "%CD% " %*

echo.

exit /B %ERRORLEVEL%
