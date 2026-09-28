# Tplscan —— 模板扫描器

PHP 8 的模板扫描器：把一个模板串切成 token 列表，供后续解析 / 渲染使用。
只依赖 PHP 标准库（php-cli 8.1+），不用 composer、不用第三方依赖、不用 mbstring。

## 用法

```php
require __DIR__ . '/autoload.php';

use Tplscan\Scanner;

$tokens = (new Scanner())->scan($template);
```

## 对外保证

以下 8 条是这份实现的契约，也是评审的依据。

1. **接口**：`scan(string $tpl)` 返回 `Token[]`；每个 token 的 `raw` 是它在模板里的原文
   （含定界符），**把所有 token 的 `raw` 顺序拼接起来，必须逐字节等于原模板**
   （覆盖全部输入字节，不丢字节、不重复）。
2. **定界符成对**：`{{ }}` 与 `{% %}` 必须成对识别；遇到**未闭合**的开定界符时，
   抛 `ScanException`，异常里带出错处的**行号**。
3. **引号感知**：`{{ }}` 内的表达式按引号感知切分——单引号或双引号**内部**出现的
   `}}`、`%}` **不算**定界符；反斜杠转义 `\'`、`\"` 生效。
4. **变量名**：变量名允许 `.` 与 `[]` 的路径写法（如 `user.name`、`items[0].id`）；
   出现其它非法字符时抛 `ScanException`，异常里带出错处的**列号**。
5. **空定界符与注释**：空定界符（`{{}}`、`{{ }}`）抛 `ScanException`；
   注释 `{# #}` 的内容**原样保留**、不参与解析。
6. **字节安全**：模板是**任意字节**（可能不是合法 UTF-8）。扫描必须按字节进行，
   不得使用会因无效 UTF-8 而出错（或返回失败）的函数。
7. **位置**：行号从 1 起、列号从 1 起；`\n` 与 `\r\n` 都要算对
   （一个 `\r\n` 只算**一次**换行）。
8. **纯函数**：同一输入多次调用结果相同；不修改入参；不依赖全局状态（不含 `setlocale`）。

## 本次交付物

`REVIEW.md`：对照上面 8 条保证通读 `src/` 两个文件，把「结论清单」表填满 ——
每个问题一行，写清它违反哪条保证、出在哪个文件::函数、以及一句可复核的证据。
`src/`、`tests/`、`check/` 一律不改。

## 目录

- `src/Scanner.php` —— 扫描器主逻辑
- `src/Token.php` —— token 与位置计算
- `tests/run.php` —— 既有回归用例
- `check/check.php` —— 固定验收（**勿改**）
- `scripts/check.sh` —— 固定验收入口
- `REVIEW.md` —— 本次要交付的评审结论

## 自检

```bash
php tests/run.php
bash scripts/check.sh            # = php check/check.php
bash scripts/check.sh -list
bash scripts/check.sh --only finding
```

`check/check.php` 支持 `-list` 与 `--only <组名>`（组名：`unmodified` / `format` / `finding`），
逐场景打印 `PASS <组>/<名>` 或 `FAIL <组>/<名>  期望=… 实际=…`，结尾打印 `结果：通过 x/N`，
全过 `exit 0`，否则 `exit 1`，失败不早退。它同时校验 `src/` 下两个源码文件的 SHA-256。
