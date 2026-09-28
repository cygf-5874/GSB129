这套扫描器是从旧项目搬过来的，想确认它能不能安全接手。tplscan 是 PHP 8 的
模板扫描器（php-cli，无 composer，无第三方依赖），构建不需要额外步骤，
自检走 `scripts/check.sh`（`check/` 是固定验收程序，别改），既有用例走 `php tests/run.php`。

README 里的「对外保证」8 条是这份实现的契约，两个源码文件里有若干处没有守住。

任务：通读源码，把 `REVIEW.md` 里那张表填满 —— 每个问题一行，
写清它违反哪一条保证、出在哪个文件的哪个函数、以及一句可判定的证据
（要能复现：别人照你说的输入能看出区别，或者能说出抛的异常类型）。

验收：
- php check/check.php 退出码 0，8 个场景全过（unmodified 1 + format 1 + finding 6）；
- 源码文件一个字节都不许改，`check/` 会校验 SHA-256。

约束：
1. 只改 `REVIEW.md`；`src/` 与 `tests/` 下的文件一律不动。
2. 不改 `check/`。
3. 不许用 composer，不许引入任何第三方库；不许用 mbstring 扩展。
4. 证据一列不要写「看起来不对」「可能有风险」这类话。
