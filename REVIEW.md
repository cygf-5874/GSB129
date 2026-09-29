# 代码审查结论 · tplscan

> 通读 `src/Scanner.php` 与 `src/Token.php`，对照 README「对外保证」的 8 条逐处核对，
> 把下表的每一行填满。**源码一个字节都不许改**（`check/` 会校验 SHA-256）。

> - 「违反保证」写被违反的那一条的**编号**（1~8）。
> - 「文件::函数」写成 `文件名::函数名`（如 `Scanner.php::scan`）。
> - 「可判定证据」写一句能复现的话：给出输入形状能看出什么区别，或能说出抛出的异常类型。
>   措辞要具体、可核对。

## 结论清单

| 编号 | 违反保证 | 文件::函数 | 可判定证据 |
| --- | --- | --- | --- |
| D1 | 3 | Scanner.php::matchDelimiter | 输入 `{{ "}}" }}` 时，匹配用的 `/\{\{(.*?)\}\}/s` 不是引号感知正则，双引号内部的 `}}` 被当成结束定界符提前闭合，inner 为 `"` 并抛 ScanException「非法变量名 "」；契约要求引号内的 `}}` 不算定界符、应得到 1 个 value 为 `"}}"` 的 var token，`\'`、`\"` 反斜杠转义同样未实现。 |
| D2 | 2 | Scanner.php::scan | 输入未闭合的开定界符 `{{ name`（没有 `}}`）时，matchDelimiter 返回 null，scan 直接 `return null`，既不抛 ScanException 也不带行号；契约要求遇到未闭合定界符时抛带行号的 ScanException。 |
| D3 | 7 | Token.php::positionAt | 行号写成 `substr_count("\n") + substr_count("\r") + 1`，一个 `\r\n` 被数两次：对 `a\r\n{{ v }}` 中偏移 3 的 `{{` 返回行号 3（应为 2）；列号恒为硬编码的 `$column = 0`，例如首行 `{{ a-b }}` 抛出的异常列号是 0，契约要求列号从 1 起算。 |
| D4 | 6 | Scanner.php::scan | scan 开头用 `preg_match('//u', $tpl)` 做 UTF-8 合法性检查，不合法就 `return []`：输入含非法字节的 `"\xFF"` 或 `"ab\xFFcd"` 时静默返回空数组、全部字节被丢弃（raw 拼接为空串而非原模板），契约要求按字节扫描、覆盖任意字节。 |
| D5 | 5 | Scanner.php::token | 空定界符 `{{}}` 与 `{{ }}` 经 `trim($inner)` 后 value 为空串，仍被正常构造成 var token 返回，不抛任何异常；契约要求这两种空定界符输入抛 ScanException。 |
| D6 | 8（连带 1） | Scanner.php::scan | token 列表存在 `private static array $tokens` 上，scan 只追加不清空：不调用 reset() 时连续两次 `scan('a')`，第一次返回 1 个 token、第二次返回 2 个 token（raw 为 `["a","a"]`），同一输入多次调用结果不同；第二次结果拼 raw 得到 `"aa"` 而非 `"a"`，连带违反保证 1。 |
