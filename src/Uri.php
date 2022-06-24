<?php

namespace PajuranCodes\Http\Message;

use function rtrim;
use function ltrim;
use function strpos;
use function parse_url;
use function strtolower;
use function rawurlencode;
use function str_starts_with;
use function preg_replace_callback;
use Psr\Http\Message\UriInterface;

/**
 * A value object representing a URI (Uniform Resource Identifier).
 *
 * {@inheritDoc}
 *
 * @link https://tools.ietf.org/html/rfc3986 Uniform Resource Identifier (URI): Generic Syntax
 * @link https://tools.ietf.org/html/rfc3986#section-3 3. Syntax Components
 * @link https://tools.ietf.org/html/rfc3986#section-2 Characters
 * @link https://en.wikipedia.org/wiki/Percent-encoding Percent-encoding
 * @link https://www.php.net/manual/en/pcre.pattern.php PCRE Patterns
 * @link https://en.wikipedia.org/wiki/Backus%E2%80%93Naur_form Backus–Naur form
 * @link https://regex101.com/ Regular expressions converter - for testing
 *
 * @author pajurancodes
 */
class Uri implements UriInterface {

    /**
     * A set of characters allowed in a URI, but without a reserved purpose.
     * 
     *  unreserved = ALPHA / DIGIT / "-" / "." / "_" / "~"
     * 
     * @link https://tools.ietf.org/html/rfc3986#section-2.3 2.3. Unreserved Characters
     * 
     * @var string
     */
    private const UNRESERVED_CHARACTERS = 'a-zA-Z0-9\-\._~';

    /**
     * A subset of reserved characters used as delimiters in a URI.
     * 
     *  reserved      = gen-delims / sub-delims
     *  gen-delims    = ":" / "/" / "?" / "#" / "[" / "]" / "@"
     *
     * @link https://tools.ietf.org/html/rfc3986#section-2.2 2.2. Reserved Characters
     * 
     * @var string
     */
    private const RESERVED_CHARACTERS_GEN_DELIMS = ':\/\?\#\[\]@';

    /**
     * A subset of reserved characters not used as delimiters in a URI.
     *
     *  reserved      = gen-delims / sub-delims
     *  sub-delims    = "!" / "$" / "&" / "'" / "(" / ")" / "*" / "+" / "," / ";" / "="
     *
     * @link https://tools.ietf.org/html/rfc3986#section-2.2 2.2. Reserved Characters
     * 
     * @var string
     */
    private const RESERVED_CHARACTERS_SUB_DELIMS = '!\$&\'\(\)\*\+,;=';

    /**
     * A list of allowed URI schemes along with their default URI ports.
     * 
     * @link https://tools.ietf.org/html/rfc3986#section-3.1 3.1. Scheme
     * @link https://tools.ietf.org/html/rfc3986#section-3.2.3 3.2.3. Port
     * 
     * @var int[]
     */
    private const ALLOWED_SCHEMES_AND_PORTS = [
        'http' => 80,
        'https' => 443,
    ];

    /**
     * A URI scheme.
     * 
     * @link https://tools.ietf.org/html/rfc3986#section-3.1 3.1. Scheme
     * 
     * @var string
     */
    private string $scheme;

    /**
     * A URI user info.
     * 
     * This component is part of the authority component.
     * 
     * @link https://tools.ietf.org/html/rfc3986#section-3.2 3.2. Authority
     * @link https://tools.ietf.org/html/rfc3986#section-3.2.1 3.2.1. User Information
     *
     * @var string
     */
    private string $userInfo;

    /**
     * A URI host.
     * 
     * This component is part of the authority component.
     * 
     * @link https://tools.ietf.org/html/rfc3986#section-3.2 3.2. Authority
     * @link https://tools.ietf.org/html/rfc3986#section-3.2.2 3.2.2. Host
     * 
     * @var string
     */
    private string $host;

    /**
     * A URI port.
     * 
     * This component is part of the authority component.
     * 
     * @link https://tools.ietf.org/html/rfc3986#section-3.2 3.2. Authority
     * @link https://tools.ietf.org/html/rfc3986#section-3.2.3 3.2.3. Port
     *
     * @var null|int
     */
    private ?int $port;

    /**
     * A URI path.
     * 
     * @link https://tools.ietf.org/html/rfc3986#section-3.3 3.3. Path
     *
     * @var string
     */
    private string $path;

    /**
     * A URI query.
     * 
     * This component is indicated by the first question
     * mark ("?") character and terminated by a number 
     * sign ("#") character or by the end of the URI.
     * 
     * @link https://tools.ietf.org/html/rfc3986#section-3.4 3.4. Query
     * 
     * @var string
     */
    private string $query;

    /**
     * A URI fragment.
     * 
     * This component is indicated by the presence of 
     * a number sign ("#") character and terminated
     * by the end of the URI.
     * 
     * @link https://tools.ietf.org/html/rfc3986#section-3.5 3.5. Fragment
     *
     * @var string
     */
    private string $fragment;

    /**
     *
     * @param string $uri (optional) A URI.
     */
    public function __construct(
        private readonly string $uri = ''
    ) {
        $uriComponents = $this->parseUri();

        $scheme = $uriComponents['scheme'] ?? '';
        $user = $uriComponents['user'] ?? '';
        $pass = $uriComponents['pass'] ?? '';
        $host = $uriComponents['host'] ?? '';
        $port = $uriComponents['port'] ?? null;
        $path = $uriComponents['path'] ?? '';
        $query = $uriComponents['query'] ?? '';
        $fragment = $uriComponents['fragment'] ?? '';

        $this->scheme = $this->buildScheme($scheme);
        $this->userInfo = $this->buildUserInfo($user, $pass);
        $this->host = $this->buildHost($host);
        $this->port = $this->buildPort($port);
        $this->path = $this->buildPath($path);
        $this->query = $this->buildQuery($query);
        $this->fragment = $this->buildFragment($fragment);
    }

    /**
     * @inheritDoc
     */
    public function getScheme() {
        return $this->scheme;
    }

    /**
     * @inheritDoc
     */
    public function withScheme($scheme) {
        $builtScheme = $this->buildScheme($scheme);

        $clone = clone $this;
        $clone->scheme = $builtScheme;
        return $clone;
    }

    /**
     * @inheritDoc
     */
    public function getUserInfo() {
        return $this->userInfo;
    }

    /**
     * @inheritDoc
     */
    public function withUserInfo($user, $password = null) {
        $builtUserInfo = $this->buildUserInfo($user, $password);

        $clone = clone $this;
        $clone->userInfo = $builtUserInfo;
        return $clone;
    }

    /**
     * @inheritDoc
     */
    public function getHost() {
        return $this->host;
    }

    /**
     * @inheritDoc
     */
    public function withHost($host) {
        $builtHost = $this->buildHost($host);

        $clone = clone $this;
        $clone->host = $builtHost;
        return $clone;
    }

    /**
     * @inheritDoc
     */
    public function getPort() {
        return $this->port;
    }

    /**
     * @inheritDoc
     */
    public function withPort($port) {
        $builtPort = $this->buildPort($port);

        $clone = clone $this;
        $clone->port = $builtPort;
        return $clone;
    }

    /**
     * @inheritDoc
     */
    public function getAuthority() {
        // The host is mandatory.
        if (empty($this->host)) {
            return '';
        }

        $authority = $this->host;

        // Prefix the authority component with the user info.
        if (!empty($this->userInfo)) {
            $authority = $this->userInfo . '@' . $authority;
        }

        // Suffix the authority component with the port number.
        if (isset($this->port)) {
            $authority = $authority . ':' . $this->port;
        }

        return $authority;
    }

    /**
     * @inheritDoc
     */
    public function getPath() {
        return $this->path;
    }

    /**
     * @inheritDoc
     */
    public function withPath($path) {
        $builtPath = $this->buildPath($path);

        $clone = clone $this;
        $clone->path = $builtPath;
        return $clone;
    }

    /**
     * @inheritDoc
     */
    public function getQuery() {
        return $this->query;
    }

    /**
     * @inheritDoc
     */
    public function withQuery($query) {
        $builtQuery = $this->buildQuery($query);

        $clone = clone $this;
        $clone->query = $builtQuery;
        return $clone;
    }

    /**
     * @inheritDoc
     */
    public function getFragment() {
        return $this->fragment;
    }

    /**
     * @inheritDoc
     */
    public function withFragment($fragment) {
        $builtFragment = $this->buildFragment($fragment);

        $clone = clone $this;
        $clone->fragment = $builtFragment;
        return $clone;
    }

    /**
     * @inheritDoc
     */
    public function __toString() {
        /*
         * Initialize a URI string, in order to be returned 
         * after all other URI components are appended to it.
         */
        $uri = '';

        // Suffix the URI string with the URI scheme.
        if (!empty($this->scheme)) {
            $uri .= $this->scheme . ':';
        }

        /*
         * Suffix the URI string with the authority component of the URI.
         */

        $authority = $this->getAuthority();

        if (!empty($authority)) {
            $uri .= '//' . $authority;
        }

        /*
         * Suffix the URI string with the URI path.
         */

        $path = $this->path;

        if (!empty($path)) {
            $firstSlashOccurrence = strpos($path, '/');

            if (
                $firstSlashOccurrence === false || // No slash was found inside the path.
                $firstSlashOccurrence !== 0 // A slash was found inside the path.
            ) { // The path is rootless.
                /*
                 * If the path is rootless and an authority is present, 
                 * the path MUST be prefixed by "/". A rootless path is 
                 * in the form:
                 *
                 *  path-rootless = segment-nz *( "/" segment )
                 *      segment    = *pchar
                 *      segment-nz = 1*pchar
                 */
                if (!empty($authority)) { // The authority is present.
                    $path = '/' . $path;
                }
            } else { // The path starts with at least one leading slash.
                /*
                 * If the path is starting with more than one slashes 
                 * and no authority is present, the starting slashes 
                 * MUST be reduced to one.
                 */
                if (empty($authority)) { // No authority is present.
                    $path = '/' . ltrim($path, '/');
                }
            }
        } else {
            $path = '/';
        }

        $uri .= $path;

        // Suffix the URI string with the URI query.
        if (!empty($this->query)) {
            $uri .= '?' . $this->query;
        }

        // Suffix the URI string with the URI fragment.
        if (!empty($this->fragment)) {
            $uri .= '#' . $this->fragment;
        }

        return $uri;
    }

    /**
     * Parse the URI and return its components.
     * 
     * @return (string|int)[] The components of the parsed URI.
     * @throws \UnexpectedValueException Failed URI parsing.
     */
    private function parseUri(): array {
        $uriComponents = parse_url($this->uri);

        if ($uriComponents === false) {
            throw new \UnexpectedValueException(
                    'The URI "' . $this->uri . '" could not be parsed.'
            );
        }

        return $uriComponents;
    }

    /**
     * Build the URI scheme.
     * 
     * The returned scheme is normalized to lowercase 
     * and contains no trailing ":" character.
     * 
     * @param string $scheme A URI scheme.
     * @return string The URI scheme.
     * @throws \InvalidArgumentException The URI scheme is not supported.
     */
    private function buildScheme(string $scheme): string {
        if (empty($scheme)) {
            return '';
        }

        $schemeWithoutTrailingColon = rtrim($scheme, ':');
        $schemeToLower = strtolower($schemeWithoutTrailingColon);

        if (!isset(self::ALLOWED_SCHEMES_AND_PORTS[$schemeToLower])) {
            throw new \InvalidArgumentException(
                    'The URI scheme "' . $scheme . '" is not supported.'
            );
        }

        return $schemeToLower;
    }

    /**
     * Build the URI user information.
     *
     * @param string $user A user name to use for the URI authority.
     * @param null|string $password A password associated with the user name.
     * @return string The URI user information, in "username[:password]" format.
     */
    private function buildUserInfo(string $user, ?string $password): string {
        if (empty($user)) {
            return '';
        }

        $userInfo = $user;

        if (!empty($password)) {
            $userInfo .= ':' . $password;
        }

        return $userInfo;
    }

    /**
     * Build the URI host.
     * 
     * The returned host is normalized to lowercase.
     *
     * @param string $host A hostname.
     * @return string The URI host.
     */
    private function buildHost(string $host): string {
        return empty($host) ? '' : strtolower($host);
    }

    /**
     * Build the URI port.
     * 
     * If no port is present, a null value is returned.
     * 
     * An exception is raised if the port resides 
     * outside the established TCP and UDP port ranges.
     * 
     * If the port is the standard port used with the 
     * current scheme, a null value is returned.
     * 
     * If the port is non-standard for the 
     * current scheme, then it is returned.
     *
     * @param null|int $port A port number; a null value removes the port information.
     * @return null|int The URI port.
     * @throws \InvalidArgumentException The port resides outside the 
     * established TCP and UDP port ranges, which span between 1 and 65535.
     */
    private function buildPort(?int $port): ?int {
        if (!isset($port)) {
            return null;
        }

        if ($port < 1 || $port > 65535) {
            throw new \InvalidArgumentException(
                    'The URI port must be an integer between 1 and 65535.'
            );
        }

        if ($this->isStandardPort($port)) {
            return null;
        }

        return $port;
    }

    /**
     * Check if a port is the standard 
     * one for the current URI scheme.
     * 
     * A passed null value means that the 
     * given port is the standard one.
     * 
     * @param null|int $port A port number.
     * @return bool True if the port is the standard one, or false otherwise.
     */
    private function isStandardPort(?int $port): bool {
        if (!isset($port)) {
            return true;
        }

        if (
            !empty($this->scheme) &&
            isset(self::ALLOWED_SCHEMES_AND_PORTS[$this->scheme]) &&
            self::ALLOWED_SCHEMES_AND_PORTS[$this->scheme] === $port
        ) {
            return true;
        }

        return false;
    }

    /**
     * Build the URI path.
     * 
     * If the URI path is present and starts with a slash ("/"), 
     * then an eventual double slash ("//") is eliminated from the 
     * beginning of it and only a single slash ("/") is retained.
     * 
     * The returned path is percent-encoded, but not double-encoded.
     *
     * @param string $path A URI path.
     * @return string The percent-encoded URI path.
     */
    private function buildPath(string $path): string {
        if (empty($path)) {
            return '';
        }

        if (str_starts_with($path, '/')) {
            $path = '/' . ltrim($path, '/');
        }

        return $this->percentEncodePath($path);
    }

    /**
     * Percent-encode all characters of a URI path, with exceptions.
     * 
     * The exceptions are:
     * 
     *  - the already percent-encoded characters;
     *  - the unreserved characters;
     *  - the reserved characters of the subset "sub-delims".
     *  - the reserved characters of the subset "gen-delims";
     *
     * The new line is also encoded.
     * 
     * Used PCRE regex syntax:
     * 
     * "( )": Subpattern.
     * Read {@link https://www.php.net/manual/en/regexp.reference.subpatterns.php PCRE regex syntax > Subpatterns}
     *
     * "(?: )": Non-capturing group. The subpattern does not do any capturing, 
     * and is not counted when computing the number of any subsequent capturing 
     * subpatterns.
     * Read {@link https://www.php.net/manual/en/regexp.reference.subpatterns.php PCRE regex syntax > Subpatterns}
     *
     * "[ ]": Character class. Matches a single character in the subject; 
     * the character must be in the set of characters defined by the class,
     * unless the first character in the class is a circumflex (^), in which 
     * case the subject character must not be in the set defined by the class.
     * Read {@link https://www.php.net/manual/en/regexp.reference.character-classes.php PCRE regex syntax > Character classes}
     * 
     * "[^ ]": Circumflex in a character class: subject character must not 
     * be in the set defined by the class.
     * Read {@link https://www.php.net/manual/en/regexp.reference.character-classes.php PCRE regex syntax > Character classes}
     * 
     * "+": Quantifier (greedy) or  "++" - Quantifier (possessive).
     * Matches between one and unlimited times, as many times as possible, 
     * without giving back.
     * Read {@link https://www.php.net/manual/en/regexp.reference.repetition.php PCRE regex syntax > Repetition}
     * 
     * "|": Vertical bar character used to separate alternative patterns.
     * Read {@link https://www.php.net/manual/en/regexp.reference.alternation.php PCRE regex syntax > Alternation}
     * 
     * "(?! )": "Lookahead" (negative) assertion.
     * Read {@link https://www.php.net/manual/en/regexp.reference.assertions.php PCRE regex syntax > Assertions}
     * 
     * "{x,y}" - General repetition quantifier.
     * Read {@link https://www.php.net/manual/en/regexp.reference.repetition.php PCRE regex syntax > Repetition}
     *
     * @link https://www.php.net/manual/en/pcre.pattern.php PCRE Patterns
     * @link https://en.wikipedia.org/wiki/Percent-encoding Percent-encoding
     * @link https://tools.ietf.org/html/rfc3986#section-2 Characters
     * @link https://tools.ietf.org/html/rfc3986#section-3.3 Path
     * @link http://www.rexegg.com/backtracking-control-verbs.html#skipfail Using (*SKIP)(*FAIL) to Exclude Unwanted Matches
     *
     * @param string $path A URI path.
     * @return string The percent-encoded URI path.
     */
    private function percentEncodePath(string $path): string {
        $pattern = '/(?:%[A-Fa-f0-9]{2}|['
            . self::UNRESERVED_CHARACTERS
            . self::RESERVED_CHARACTERS_SUB_DELIMS
            . self::RESERVED_CHARACTERS_GEN_DELIMS
            . '])(*SKIP)(*FAIL)|./us'
        ;

        return preg_replace_callback($pattern, function ($matches) {
            return rawurlencode($matches[0]);
        }, $path);
    }

    /**
     * Build the URI query.
     * 
     * The leading "?" character is removed from the query.
     * 
     * The returned URI query is percent-encoded, but not double-encoded.
     * 
     * @param string $query A URI query.
     * @return string The percent-encoded URI query.
     */
    private function buildQuery(string $query): string {
        if (empty($query)) {
            return '';
        }

        $queryWithoutLeadingQuestionMark = ltrim($query, '?');

        return $this->percentEncodeQuery($queryWithoutLeadingQuestionMark);
    }

    /**
     * Percent-encode all characters of a URI query, with exceptions.
     * 
     * The exceptions are:
     * 
     *  - the already percent-encoded characters;
     *  - the unreserved characters;
     *  - the reserved characters of the subset "sub-delims".
     *  - the reserved characters of the subset "gen-delims";
     * 
     * The new line is also encoded.
     * 
     * @see percentEncodePath() For a description of the used PCRE regex syntax.
     * 
     * @link https://www.php.net/manual/en/pcre.pattern.php PCRE Patterns
     * @link https://en.wikipedia.org/wiki/Percent-encoding Percent-encoding
     * @link https://tools.ietf.org/html/rfc3986#section-2 Characters
     * @link https://tools.ietf.org/html/rfc3986#section-3.4 Query
     * @link http://www.rexegg.com/backtracking-control-verbs.html#skipfail Using (*SKIP)(*FAIL) to Exclude Unwanted Matches
     * 
     * @param string $query A URI query.
     * @return string The percent-encoded URI query.
     */
    private function percentEncodeQuery(string $query): string {
        $pattern = '/(?:%[A-Fa-f0-9]{2}|['
            . self::UNRESERVED_CHARACTERS
            . self::RESERVED_CHARACTERS_SUB_DELIMS
            . self::RESERVED_CHARACTERS_GEN_DELIMS
            . '])(*SKIP)(*FAIL)|./us'
        ;

        return preg_replace_callback($pattern, function ($matches) {
            return rawurlencode($matches[0]);
        }, $query);
    }

    /**
     * Build the URI fragment.
     * 
     * The leading "#" character is removed from the fragment.
     * 
     * The returned URI fragment is percent-encoded, but not double-encoded.
     *
     * @param string $fragment A URI fragment.
     * @return string The percent-encoded URI fragment.
     */
    private function buildFragment(string $fragment): string {
        if (empty($fragment)) {
            return '';
        }

        $fragmentWithoutLeadingNumberSign = ltrim($fragment, '#');

        return $this->percentEncodeFragment($fragmentWithoutLeadingNumberSign);
    }

    /**
     * Percent-encode all characters of a URI fragment, with exceptions.
     * 
     * The exceptions are:
     * 
     *  - the already percent-encoded characters;
     *  - the unreserved characters;
     *  - the reserved characters of the subset "sub-delims".
     *  - the reserved characters of the subset "gen-delims";
     * 
     * The new line is also encoded.
     * 
     * @see percentEncodePath() For a description of the used PCRE regex syntax.
     * 
     * @link https://www.php.net/manual/en/pcre.pattern.php PCRE Patterns
     * @link https://en.wikipedia.org/wiki/Percent-encoding Percent-encoding
     * @link https://tools.ietf.org/html/rfc3986#section-2 Characters
     * @link https://tools.ietf.org/html/rfc3986#section-3.5 Fragment
     * @link http://www.rexegg.com/backtracking-control-verbs.html#skipfail Using (*SKIP)(*FAIL) to Exclude Unwanted Matches
     *
     * @param string $fragment A URI fragment.
     * @return string The percent-encoded URI fragment.
     */
    private function percentEncodeFragment(string $fragment): string {
        $pattern = '/(?:%[A-Fa-f0-9]{2}|['
            . self::UNRESERVED_CHARACTERS
            . self::RESERVED_CHARACTERS_SUB_DELIMS
            . self::RESERVED_CHARACTERS_GEN_DELIMS
            . '])(*SKIP)(*FAIL)|./us'
        ;

        return preg_replace_callback($pattern, function ($matches) {
            return rawurlencode($matches[0]);
        }, $fragment);
    }

}
