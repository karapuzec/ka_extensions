set ext_code=ka_extensions
set ext_dir=F:\xampp72\ka.local\ka_ext\oc3000a1

set dest_dir=F:\xampp72\htdocs\oc_tests\oc3031

xcopy /E /Y %ext_dir%\admin\controller\extension\ka_extensions\%ext_code% %dest_dir%\admin\controller\extension\ka_extensions\%ext_code%
xcopy /E /Y %ext_dir%\admin\model\extension\ka_extensions\%ext_code% %dest_dir%\admin\model\extension\ka_extensions\%ext_code%
xcopy /E /Y %ext_dir%\admin\view\template\extension\ka_extensions\%ext_code% %dest_dir%\admin\view\template\extension\ka_extensions\%ext_code%
xcopy /E /Y %ext_dir%\admin\view\template\extension\ka_extensions\common %dest_dir%\admin\view\template\extension\ka_extensions\common

xcopy /E /Y %ext_dir%\catalog\controller\extension\ka_extensions\%ext_code% %dest_dir%\catalog\controller\extension\ka_extensions\%ext_code%
xcopy /E /Y %ext_dir%\catalog\model\extension\ka_extensions\%ext_code% %dest_dir%\catalog\model\extension\ka_extensions\%ext_code%

xcopy /E /Y %ext_dir%\catalog\view\theme\default\template\extension\ka_extensions\%ext_code% %dest_dir%\catalog\view\theme\default\extension\ka_extensions\%ext_code%
xcopy /E /Y %ext_dir%\catalog\view\theme\milena\template\extension\ka_extensions\%ext_code% %dest_dir%\catalog\view\theme\milena\extension\ka_extensions\%ext_code%
xcopy /E /Y %ext_dir%\catalog\view\theme\default\template\extension\ka_extensions\common %dest_dir%\catalog\view\theme\default\template\extension\ka_extensions\common

xcopy /E /Y %ext_dir%\system\library\extension\ka_extensions\* %dest_dir%\system\library\extension\ka_extensions\