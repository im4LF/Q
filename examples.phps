<?php
require './Q.php';

// setup dsn, connect to db, set "default" alias, set table prefix
QF('mysql://user:pass@host/dbname')->connect()->alias('default')->tablePrefix('project__');

// execute query, fetch row and free result
// ?i - integer
$res = Q('SELECT * FROM #users WHERE id = ?i', array(1));
/*
-- builded query:
SELECT * FROM project__users WHERE id = 1
*/

$row = $res->row();

__($row);

// execute query and get result
$res = Q('SELECT * FROM #users');
// get each row, when all rows will be getting - result will be freeing automatically
while ($row = $res->each())
{
	__($row);
}

// execute query, get all rows and free result
$all = Q('SELECT * FROM #users WHERE id > ?i AND id < ?i', array(0, 30))->all('id');
__($all);
/*
-- builded query:
SELECT * FROM project__users WHERE id > 0 AND id < 30
*/

// insert set of data by using one template
// ?i - integer
// ?s - string (mysql_real_escape_string)
// ?x - auto detect type
// ?e - eval - no changes
Q('INSERT INTO #test VALUES (?i, ?s, ?x, ?e)
		ON DUPLICATE KEY UPDATE
			a = VALUES(a)*?x', array(
	array(10, 'str11', 'str12', 'a*10'),
	array(20, 'str21', 'str22', 'a*20'),
	50,
	array(30, 'str31', 'str32', 'a*30')		
));
/*
-- builded query:
INSERT INTO project__test VALUES 
		(10, 'str11', 'str12', a*10), 
		(10, 'str21', 'str22', a*20), 
		(10, 'str31', 'str32', a*30)
	ON DUPLICATE KEY UPDATE
		a = VALUES(a)*50
*/

// connect to other db and set alias "other-db"
QF('mysql://user:pass@host/dbname2?charset=cp1251')->alias('other-db')->connect()->tablePrefix('bb__');

// execute query on other db, get all rows and free result
$all = Q('other-db: SHOW TABLES')->all();

__($all);

// or u can use connection as a variable
$db = QF('mysql://user:pass@host/dbname?charset=cp1251')->connect()->tablePrefix('aa__');
__($db->query('SELECT * FROM #registration_document_types')->all());

function __($var)
{
	echo '<pre>'.print_r($var, 1).'</pre>';
}
?>