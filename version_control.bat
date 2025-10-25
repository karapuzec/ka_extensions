@SET VERSION=4.1.1.22
@SET SVNCOMMAND=svn propset svn:keywords ^"Revision Version=%VERSION% Project=Ka%%_Extensions Author=karapuz%%_team%%_^<support@ka-station.com^>^"
@REM @echo %SVNCOMMAND%
%SVNCOMMAND%       catalog\model\catalog\ka_price_match.php
%SVNCOMMAND%       system\library\kamail.php
%SVNCOMMAND%       system\library\kaformat.php
%SVNCOMMAND%       system\library\kapagination.php
%SVNCOMMAND%       system\library\kaiterator.php
%SVNCOMMAND%       system\library\kainstaller.php
%SVNCOMMAND%       system\library\pagination.php
%SVNCOMMAND%       system\library\kacontroller.php
%SVNCOMMAND%       system\library\kadb.php
%SVNCOMMAND%       system\library\kafileutf8.php
%SVNCOMMAND%       system\library\kaglobal.php
%SVNCOMMAND%       system\library\kamodel.php
%SVNCOMMAND%       system\library\kacurl.php
%SVNCOMMAND%       system\library\kalanguage.php
%SVNCOMMAND%       system\library\kastore.php

%SVNCOMMAND%       system\library\template\twig\extension\kaextensions.php
%SVNCOMMAND%       system\library\Twig\Extension\KaExtensions.php

%SVNCOMMAND%       admin\model\extension\ka_extensions.php
%SVNCOMMAND%       admin\model\extension\ka_extensions

%SVNCOMMAND%       admin\controller\extension\ka_extensions
%SVNCOMMAND%       admin\controller\extension\extension\ka_extensions.php

%SVNCOMMAND%       admin\language\en-gb\ka_format.php
%SVNCOMMAND%       admin\language\en-gb\extension\extension\ka_extensions.php

%SVNCOMMAND%       admin\view\javascript\ka.alert.js
%SVNCOMMAND%       admin\view\template\extension\ka_extensions\common\ka_top.twig
%SVNCOMMAND%       admin\view\template\extension\ka_extensions\common\select.twig
%SVNCOMMAND%       admin\view\template\extension\ka_extensions\common\ka_breadcrumbs.twig
%SVNCOMMAND%       admin\view\template\extension\ka_extensions\ka_ext\input_key.twig
%SVNCOMMAND%       admin\view\template\extension\extension\ka_extensions.twig
%SVNCOMMAND%       changelog.txt
%SVNCOMMAND%       catalog\view\theme\default\template\extension\ka_extensions\common\ka_top.twig
%SVNCOMMAND%       catalog\view\theme\default\template\extension\ka_extensions\common\select.twig
%SVNCOMMAND%       catalog\view\theme\default\template\extension\ka_extensions\common\ka_breadcrumbs.twig
%SVNCOMMAND%       system\library\extension\ka_extensions\controllerinstaller.php