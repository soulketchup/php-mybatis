<?php

include "../src/autoloader.php";

use Ketchup\MyBatis\Mapper;


$xml = '<mapper namespace="test">
<select id="select1">
select * from test
<where>
<if test="searchValue != null and !empty(searchValue)">
<choose>
<when test="searchColumn eq \'subject\'">subject like #{searchValue}</when>
<when test="searchColumn eq \'contents\'">contents like #{searchValue}</when>
<otherwise>subject like #{searchValue} or contents like #{searchValue}</otherwise>
</choose>
</if>
</where>
</select>
<select id="nestedSelect" parameterType="Parameter" resultType="map">
select *
from names
<where>
	<foreach collection="names" item="name" separator="or">
		<foreach collection="name.firstNames" item="firstName" separator="or">
			(lastName = #{name.lastName} and firstName = #{firstName})
		</foreach>
	</foreach>
</where>
</select>
<insert id="insert1">
insert into test
<set>
<if test="subject != null">,subject=#{subject}</if>
<if test="contents != null">,contents=#{contents}</if>
</set>
</insert>
</mapper>';

$dom = dom_import_simplexml(simplexml_load_string($xml));

// function parse($elem, $parent = NULL) {
// 	$current = NULL;
// 	switch ($elem->nodeType) {
// 		case XML_TEXT_NODE:
// 		case XML_CDATA_SECTION_NODE:
// 			$text = trim($elem->textContent);
// 			if ($text) {
// 				$current = new TextStatement($text);
// 				if ($parent) {
// 					$parent->append($current);
// 				}
// 			}
// 			return $current;
// 	}
// 	$nodeName = $elem->nodeName;
// 	switch ($nodeName) {
// 		case 'select':
// 			$current = new SelectStatement($elem->getAttribute('id'), $elem->getAttribute('resultType'));
// 			break;
// 		case 'insert':
// 			$current = new InsertStatement($elem->getAttribute('id'), $elem->getAttribute('useGeneratedKeys'), $elem->getAttribute('keyProperty'));
// 			break;
// 		case 'foreach':
// 			$current = new ForEachStatement($elem->getAttribute('collection'), $elem->getAttribute('item'), $elem->getAttribute('index'), $elem->getAttribute('open'), $elem->getAttribute('close'), $elem->getAttribute('separator'));
// 			break;
// 		case 'choose':
// 			$current = new ChooseStatement();
// 			break;
// 		case 'when':
// 			$current = new WhenStatement($elem->getAttribute('test'));
// 			break;
// 		case 'otherwise':
// 			$current = new OtherwiseStatement();
// 			break;
// 		case 'if':
// 			$current = new IfStatement($elem->getAttribute('test'));
// 			break;
// 		case 'set':
// 			$current = new SetStatement();
// 			break;
// 		case 'where':
// 			$current = new WhereStatement();
// 			break;
// 	}
// 	if ($current) {
// 		if ($parent) {
// 			$parent->append($current);
// 		}
// 		if ($elem->hasChildNodes()) {
// 			foreach ($elem->childNodes as $child) {
// 				parse($child, $current);
// 			}
// 		}
// 	}
// 	return $current;
// }

$context = [
	'subject' => null,
	'contents' => 'contents',
	'searchColumn' => 'subject',
	'searchValue' => '%test%',
	'names' => [
		'name' => [
			'firstNames' => ['Bar', 'Baz'],
			'lastName' => 'Foo'
		]
	]
];

/** @var Mapper $mapper */
$mapper = new Mapper();
$mapper->initXml($dom);
var_dump($mapper->getSql('select', 'test.nestedSelect', $context, $param), $param);

// foreach ($dom->getElementsByTagName('insert') as $node) {
// 	$sql = parse($node);
// 	$param = [];
// 	$query = $sql->parse($context, $param);
// 	echo $query;
// 	echo '<br>';
// 	var_dump($param);
// 	echo '<hr>';
// }
