# php-mybatis
php mybatis implementation
# Description
simple mybatis implemetation.

tested php version - 5.6.31-x86

tested database vendor - mysql 5.x
# Requirements
\Psr\Log, \PDO
# Available Features
- configuration
  - environments[@default]
    - environment[@id]
      - dataSource
        - property[@name][@value]
  - mappers
    - mapper[@resource]
- mapper[@namespace]
  - sql[@id]
  - select[@id][@resultType]
  - insert[@id][@useGeneratedKeys][@keyProperty]
    - selectKey[@keyProperty][@resultType][@order]
  - update[@id]
  - delete[@id]
  - include[@refid]
  - if[@test]
  - choose
    - when[@test]
    - otherwise
  - where
  - set
  - foreach[@item][@index][@collection][@open][@separator][@close]
