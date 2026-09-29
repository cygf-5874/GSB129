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
| D1 | 3 | Scanner.php::matchDelimiter | 输入 `{{ "x}}" }}` 抛 ScanException「非法变量名 "x」（引号内的 `}}` 被当结束定界符，表达式提前闭合）；输入 `{% if s=="x%}" %}` 被切成 tag `if s=="x` 加后续文本，说明三个正则不做引号感知、`\'`/`\"` 转义也不生效。 |
| D2 | 2 | Scanner.php::scan | 输入 `abc {{ x`（`{{` 未闭合、无 `}}`）时 scan 返回 `null`，不抛 ScanException，调用方拿不到契约要求的异常与出错行号。 |
| D3 | 7 | Token.php::positionAt | `positionAt("abc", 1)` 返回列号 0（契约要求列从 1 起）；对 `"a\r\nb"` 的偏移 3 返回行号 3，因为代码对前缀分别 substr_count `\n` 和 `\r`，一个 `\r\n` 被算了两次换行（应为行号 2）。 |
| D4 | 6 | Scanner.php::scan | 输入含无效 UTF-8 字节的 `abc\xFFdef` 时，scan 开头的 `preg_match('//u', $tpl)` 不等于 1，函数直接返回空数组 `[]`，全部字节被丢弃，而不是按字节扫描并覆盖这些字节。 |
| D5 | 5 | Scanner.php::token | 输入 `x{{}}y` 不抛 ScanException，返回 text、value 为空串的 var、text 三个 token；`x{{ }}y` 经 trim 后同为空串也照样返回 var token，空定界符未被拦截。 |
| D6 | 8（保证 1 同时失效） | Scanner.php::scan | 不调用 reset 连续两次调用：scan('a') 得 1 个 token，随后 scan('b') 第二次返回 2 个 token、raw 拼接结果为 `ab`；静态属性 $tokens 跨多次调用追加累积，不是纯函数，且第二次结果的 raw 拼接不等于本次入参 `b`。 |
