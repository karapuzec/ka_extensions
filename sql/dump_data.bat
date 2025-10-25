set db_name="ka_ext_oc3000a1"
set db_user="root"
set db_pass=""
set db_host="localhost"

set dump_line=mysqldump -u%db_user% -h%db_host% %db_name%

%dump_line% --default-character-set=utf8 --skip-extended-insert --databases --add-drop-database "--result-file=dump.sql"

rem %dump_line% --no-create-db --skip-extended-insert --no-create-info xcart_config > xcart_config.sql
rem %dump_line% --no-create-db --skip-extended-insert --no-create-info xcart_languages > xcart_languages.sql