<?php
error_reporting(E_ALL);

require_once '../src/autoloader.php';

use Ketchup\MyBatis\SqlMap;

$xml = isset($_POST['xml']) ? $_POST['xml'] : '';
$param = isset($_POST['param']) ? $_POST['param'] : '';

if (empty($xml)) {
    $xml = trim('<?xml version="1.0" encoding="utf-8"?>
<mapper namespace="Test">
    <sql id="where">
        <where>
            <if test="search != null and !empty(search)">
            and (subject like #{search} or contents like #{search})
            </if>
        </where>
    </sql>
    <select id="Count" resultType="int">
        select count(*) from ${tableName}
        <include refid="where" />
    </select>
    <select id="List" resultType="array">
        select * from ${tableName}
        <include refid="where" />
        order by id desc
        <if test="limit != null and limit gt 0">
            limit ${limit}
        </if>
    </select>
    <insert id="Insert">
        <foreach collection="items" item="item" index="index">
            insert into ${tableName} values(#{index}, #{item.subject}, #{item.contents});
        </foreach>
    </insert>
    <insert id="Insert2">
        insert into ${tableName} (idx, subject, contents) values
        <foreach collection="items" item="item" index="index" open="(" close=");" separator="),(">
            #{index}, #{item.subject}, #{item.contents}
        </foreach>
    </insert>
    <update id="Update1">
        <foreach collection="items" item="item" index="index" separator=";">
            update ${tableName}
            <set>
                <if test="item.subject != null"> , subject = #{item.subject}</if>
                <if test="item.contents != null"> , contents = #{item.contents}</if>
            </set>
            where idx = #{index}
        </foreach>
    </update>
</mapper>');
}

if (empty($param)) {
    $param = '{
    "tableName": "posts",
    "search": "%test%",
    "items": [
        {
            "subject": "test 1",
            "contents": "contents 1"
        },
        {
            "subject": "test 2",
            "contents": "contents 2"
        }
    ],
    "limit": 10
}';
}

function resultText() {
    global $xml, $param;
    $dom = simplexml_load_string($xml);
    if (!$dom) {
        return 'XML Error';
    }
    $model = json_decode($param);
    $mapper = new SqlMap();
    $mapper->setNamedParameterPrefix('?');
    try {
        $mapper->initXml($dom);
    } catch (\Exception $e) {
        return '[xml parsing error]' . PHP_EOL;
    }
    $arr = [
        '[parameter object]',
        print_r($model, TRUE)
    ];
    foreach ($mapper->statements as $category => $statements) {
        foreach (array_keys($statements) as $id) {
            $arr[] = '[(' . $category . ') ' . $id . ']';
            $sqlParam = [];
            $statement = $mapper->getStatement($category, $id);
            $sql = $statement->parse($model, $sqlParam);
            $arr[] = $sql;
            $arr[] = print_r($sqlParam, TRUE);
            $arr[] = '[source]';
            $arr[] = $statement->__toSource();
            $arr[] = '';
        }
    }
    return implode(PHP_EOL, $arr);
}

if (isset($_POST['xml']) && isset($_POST['param'])) {
    echo resultText();
    exit;
}

?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>test</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/3.3.7/css/bootstrap.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/3.3.7/css/bootstrap-theme.min.css" />
    <style>
    #xmlEdit, #paramEdit {height:400px;}
    pre {white-space:pre-wrap;}
    </style>
</head>
<body>

    <div class="container-fluid">
        <div class="page-header">
            <h3>php mybatis test</h3>
        </div>
    
        <form action="?" method="post">
            <input type="hidden" name="xml" id="xml" value="<?= htmlspecialchars($xml) ?>">
            <input type="hidden" name="param" id="param" value="<?= htmlspecialchars($param) ?>">

            <div class="row">
                <div class="col-md-6 form-group">
                    <label class="control-label">Mapper XML</label>
                    <div id="xmlEdit"></div>
                </div>
                <div class="col-md-6 form-group">
                    <label class="control-label">Parameter Object (As JSON)</label>
                    <div id="paramEdit"></div>
                </div>
            </div>
            <div class="form-group">
                <button type="button" id="testIt" class="btn btn-primary">TEST IT!</button>
            </div>
        </form>

        <div class="row">
            <div class="col-md-12">
                <pre id="resultText"><?= resultText() ?></pre>
            </div>
        </div>

        <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/1.8.3/jquery.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.10.1/min/vs/loader.js"></script>
        <script>
            require.config({ paths: { 'vs': 'https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.10.1/min/vs/' }});
            window.MonacoEnvironment = {
                getWorkerUrl: function(workerId, label) {
                    return 'https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.10.1/min/vs/monaco-editor-worker-loader-proxy.js';
                }
            };
            require(['vs/editor/editor.main'], function() {
                editorXml = monaco.editor.create(document.getElementById("xmlEdit"), {
                    value: document.getElementById('xml').value,
                    language: "xml",
                    lineNumbers: true,
                    theme: 'vs-dark',
                    minimap: {
                        enabled: false
                    }
                });
                editorParam = monaco.editor.create(document.getElementById("paramEdit"), {
                    value: document.getElementById('param').value,
                    language: "json",
                    lineNumbers: true,
                    theme: 'vs-dark',
                    minimap: {
                        enabled: false
                    }
                });
            });

            window.onresize = function () {
                editorXml.layout();
                editorParam.layout();
            };

            $('#testIt').on('click', function (e) {
                e.preventDefault();
                $.post('?', { xml: editorXml.getValue(), param: editorParam.getValue() }, function (result) {
                    $('#resultText').hide().html(result).fadeIn();
                }, 'text');
            });
        </script>
    </div>
</body>
</html>