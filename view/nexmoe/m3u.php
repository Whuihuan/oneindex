<?php
header('Content-Type: text/json;charset=utf-8');
header('Access-Control-Allow-Origin:*'); // *���������κ���ַ����
header('Access-Control-Allow-Methods:POST,GET,OPTIONS,DELETE'); // �������������
header('Access-Control-Allow-Credentials: true'); // �����Ƿ������� cookies
header('Access-Control-Allow-Headers: Content-Type,Content-Length,Accept-Encoding,X-Requested-with, Origin'); // ���������Զ�������ͷ���ֶ�
echo $items;
?>