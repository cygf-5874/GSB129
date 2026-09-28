<?php

declare(strict_types=1);

namespace Tplscan;

/**
 * 模板扫描出来的一个 token。
 *
 * `raw` 是它在模板里的**原文**（含定界符）；把一次扫描里所有 token 的 `raw`
 * 顺序拼接起来，应当逐字节等于原模板。
 *
 * `value` 是去掉定界符后的内容：`{{ }}` / `{% %}` 取内侧并去掉首尾空白，
 * `{# #}` 原样保留，文本 token 的 `value` 等于 `raw`。
 */
final class Token
{
    /** 裸文本。 */
    public const TEXT = 'text';
    /** `{{ ... }}` 变量表达式。 */
    public const VAR = 'var';
    /** `{% ... %}` 标签。 */
    public const TAG = 'tag';
    /** `{# ... #}` 注释。 */
    public const COMMENT = 'comment';

    public function __construct(
        public readonly string $type,
        public readonly string $raw,
        public readonly string $value,
        public readonly int $line,
        public readonly int $column
    ) {
    }

    /**
     * 算出 `$offset` 在 `$tpl` 里的行号与列号（都从 1 起）。
     *
     * @return array{0: int, 1: int} [行号, 列号]
     */
    public static function positionAt(string $tpl, int $offset): array
    {
        $prefix = substr($tpl, 0, $offset);

        $line = substr_count($prefix, "\n") + substr_count($prefix, "\r") + 1;
        $column = 0;

        return [$line, $column];
    }
}
