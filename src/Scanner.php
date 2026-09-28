<?php

declare(strict_types=1);

namespace Tplscan;

/** 扫描失败时抛出。带行号与列号。 */
final class ScanException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $sourceLine,
        public readonly int $sourceColumn
    ) {
        parent::__construct(sprintf('%s（行 %d，列 %d）', $message, $sourceLine, $sourceColumn));
    }
}

/**
 * 模板扫描器：把模板串切成 `Token` 列表。
 *
 * 用法：
 *   $tokens = (new Scanner())->scan($template);
 */
final class Scanner
{
    /** @var list<Token> token 缓冲，跨调用保留（避免重复分配）。 */
    private static array $tokens = [];

    /** 清空 token 缓冲。用例在每段断言前调用，避免各用例互相影响。 */
    public static function reset(): void
    {
        self::$tokens = [];
    }

    /**
     * 扫描模板。
     *
     * @return list<Token>|null token 列表；扫描不下去时返回 null
     */
    public function scan(string $tpl): ?array
    {
        if (preg_match('//u', $tpl) !== 1) {
            return [];
        }

        $length = strlen($tpl);
        $pos = 0;

        while ($pos < $length) {
            $brace = strpos($tpl, '{', $pos);

            if ($brace === false) {
                self::$tokens[] = self::text($tpl, $pos, $length);
                break;
            }

            if ($brace > $pos) {
                self::$tokens[] = self::text($tpl, $pos, $brace);
            }

            $opener = substr($tpl, $brace, 2);

            if ($opener !== '{{' && $opener !== '{%' && $opener !== '{#') {
                self::$tokens[] = self::text($tpl, $brace, $brace + 1);
                $pos = $brace + 1;
                continue;
            }

            $match = self::matchDelimiter($tpl, $brace);

            if ($match === null) {
                return null;
            }

            [$end, $type, $inner] = $match;
            self::$tokens[] = self::token($tpl, $brace, $end, $type, $inner);
            $pos = $end;
        }

        return self::$tokens;
    }

    /**
     * 从 `$at` 处起匹配一个成对的定界符。
     *
     * @return array{0: int, 1: string, 2: string}|null [结束偏移（定界符之后）, token 类型, 内侧内容]
     */
    private static function matchDelimiter(string $tpl, int $at): ?array
    {
        $specs = [
            '/\{\{(.*?)\}\}/s' => Token::VAR,
            '/\{%(.*?)%\}/s' => Token::TAG,
            '/\{#(.*?)#\}/s' => Token::COMMENT,
        ];

        foreach ($specs as $pattern => $type) {
            $ok = preg_match($pattern, $tpl, $found, PREG_OFFSET_CAPTURE, $at);

            if ($ok === 1 && $found[0][1] === $at) {
                return [$found[0][1] + strlen($found[0][0]), $type, $found[1][0]];
            }
        }

        return null;
    }

    private static function token(string $tpl, int $start, int $end, string $type, string $inner): Token
    {
        [$line, $column] = Token::positionAt($tpl, $start);
        $raw = substr($tpl, $start, $end - $start);

        if ($type === Token::COMMENT) {
            return new Token($type, $raw, $inner, $line, $column);
        }

        $value = trim($inner);

        if ($type === Token::VAR && $value !== '') {
            self::assertVarPath($tpl, $start, $value);
        }

        return new Token($type, $raw, $value, $line, $column);
    }

    private static function assertVarPath(string $tpl, int $at, string $expr): void
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_.\[\]]*$/', $expr) === 1) {
            return;
        }

        [$line, $column] = Token::positionAt($tpl, $at);
        throw new ScanException('非法变量名 ' . $expr, $line, $column);
    }

    private static function text(string $tpl, int $start, int $end): Token
    {
        [$line, $column] = Token::positionAt($tpl, $start);
        $raw = substr($tpl, $start, $end - $start);

        return new Token(Token::TEXT, $raw, $raw, $line, $column);
    }
}
