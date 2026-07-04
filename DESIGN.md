# BEAR.Mcp 設計文書

> bear/mcp — BEAR.Sunday アプリケーションを MCP サーバーとして公開する

作成: 2026-07-05。マルチエージェント調査(BEAR.ToolUse ソース読解 / BEAR.Sunday 内部機構 / MCP PHP SDK 実地調査 / MCP 仕様・他フレームワーク先行事例)+ 3 案独立設計 + 3 視点審査の統合結果。

---

## 0. 結論: アダプタでもルータでもなく「第三のプロトコルバインディング」

| 論点 | 決定 |
|---|---|
| 基本形 | **プロトコルバインディング**(Module + bin エントリ + PSR-15 ハンドラ)。ルータではない。SchemeCollection アダプタでもない |
| リソースへの入口 | `ResourceInterface` を直接消費(BEAR.Cli の `ResourceCommand` と同型) |
| GET | MCP **resource / resource template**(正準)+ **readOnly tool**(既定で併存) |
| POST/PUT/PATCH/DELETE | **tool** + HTTP 動詞から機械導出した ToolAnnotations |
| MCP resource URI | **BEAR URI そのまま**(`app://self/todo{?id}`)。変換層ゼロ = 恒等写像 |
| 公開宣言 | 新属性 `#[Mcp]` / `#[McpExclude]`(**default-closed**、メソッド粒度 opt-in) |
| スキーマ導出 | `BEAR\Resource\OptionsMethods`(DI 注入・直呼び)+ `#[JsonSchema]` ファイル最優先 |
| SDK | 公式 **mcp/sdk ^0.6**(Runtime Handler API)。`Sdk\` 名前空間に隔離 |
| トランスポート | stdio 第一級(`vendor/bin/bear-mcp`)+ Streamable HTTP(PSR-15) |
| BEAR.ToolUse | 属性は**共有しない**。`suggest` + Interop ブリッジ。スキーマ層は将来 OptionsMethods に収斂 |
| prompts | v1 スコープ外(`#[McpPrompt]` の名前だけ予約) |

### 0.1 なぜルータではないか

`RouterInterface::match(array $globals, array $server)` — このシグネチャが答え。BEAR.Sunday のルータは **SAPI 形式の外部入力(`$GLOBALS`/`$_SERVER`)を `(method, path, query)` に翻訳する装置**で、1 リクエスト = 1 プロセスのライフサイクルに埋め込まれている。MCP はこの前提を二重に破る:

1. `tools/call` の `{name, arguments}` は最初から構造化されており、翻訳すべき「外部構文」がない。
2. MCP は 1 プロセス = N 呼び出しの永続 JSON-RPC セッションで、tools / resources / prompts / completion の 4 系統を多重化する。1 起動 1 マッチのルータとは形が合わない。

先例は既にある: **BEAR.Cli はルータを一切通さず `$this->resource->{$method}($uri, $params)` を直接呼ぶ**。リソースクライアントこそがプロトコル非依存の継ぎ目であり、MCP はその新しい消費者。

### 0.2 なぜ(狭義の)アダプタでもないか

BEAR の語彙で「リソースのアダプタ」は `SchemeCollection` に登録する `AppAdapter` 等、**URI スキーム→インスタンス解決**の装置。bear/mcp は新スキームを追加せず、既存の `app://self` を**消費**する。方向が逆。

### 0.3 正体

```
HTTP:  bin/app.php        → Router  → ResourceInterface → Responder
CLI:   bin/cli/*          → ArgParser → ResourceInterface → CommandResult
MCP:   vendor/bin/bear-mcp → McpMap → ResourceInterface → CallToolResult / ResourceContents
```

トランスポート(stdio / Streamable HTTP)が SAPI に、`McpMap` がルータの**逆写像**(パスをマッチする代わりに URI を公刊する静的宣言マップ)に、ハンドラ群が Responder に対応する。

核心: **MCP の resource URI は自由形式(RFC 3986 準拠なら何でもよい)であり、`app://self/todo` はそのまま合法な MCP URI**。仕様自身が「サーバー経由で読ませるものは custom スキームを使う SHOULD」と言う。Laravel は `weather://...` を捏造し Symfony は `time://current` を手書きするが、BEAR はアプリ内部で使ってきた URI をそのまま公開できる。**アダプタが不要なのではなく、アダプタが恒等関数になる。**

---

## 1. プリミティブ対応表 — uniform interface の配当

BEAR の `on{Verb}` 命名は RFC 9110 の safe/idempotent 意味論をメソッド名に既に符号化しており、MCP の ToolAnnotations(リスク語彙)へ**機械導出**できる。他フレームワーク(Laravel `#[IsReadOnly]`、Symfony、FastMCP)はすべて手書き注釈を要求する — BEAR だけが導出できる。

| BEAR.Sunday | MCP | 導出 / 条件 |
|---|---|---|
| GET・引数なし | **resource**(`app://self/config`) | `#[Mcp]` 付与時 |
| GET・引数あり | **resource template** `app://self/todo{?id}` + completion | `#[Mcp]` 付与時 |
| GET(併せて) | **tool**(`readOnlyHint: true`) | 既定で併存(`as:` で抑止可) |
| POST | tool — `readOnly:false, destructive:false, idempotent:false` | additive 前提・上書き可 |
| PUT | tool — `destructive:true, idempotent:true` | 置換 = 非 additive |
| PATCH | tool — `destructive:true, idempotent:false` | |
| DELETE | tool — `destructive:true, idempotent:true` | |
| (全 tool) | `openWorldHint: false` | 自アプリ = 閉領域。外部 API を叩くリソースのみ `openWorld: true` |
| OPTIONS メタデータ(`OptionsMethods`) | tool `description` + `inputSchema` | 自動 |
| `#[JsonSchema(params:)]` | `inputSchema`(ファイル内容を最優先マージ) | 自動 |
| `#[JsonSchema(schema:)]` | `outputSchema` + `structuredContent` | root が object の場合のみ |
| `#[Link]` / HAL `_links` | tool 結果の **`resource_link`** content block | 自動(v0.2) |
| ALPS semantic descriptor | パラメータ description 補強 + completion 候補 | optional(ToolUse 連携) |
| `$ro->code >= 400` / `BadRequestException` | `isError: true` + 自己修正可能なメッセージ | tools/call |
| `ResourceNotFoundException` | JSON-RPC `-32002`(resources/read)/ `isError`(tools/call) | |
| — | prompts | v1 スコープ外 |

capabilities 宣言: `tools: {}`, `resources: {}`(subscribe / listChanged なし — マップはブート時静的なので偽らない)、`completions: {}`。

**GET の二重投影を既定にする理由**: 意味論的正準は resource/template(MCP resources = application-driven な safe read)。しかし FastMCP 2.8 がデフォルトを全 TOOL に倒した事実、クライアントの resources サポートの弱さは無視できない。役割分担する — **resource = ユーザー/アプリが明示注入する文脈、readOnly tool = モデルが自律的に引く文脈**。同一 onGet の 2 つの射、実装は 1 つ。`readOnlyHint: true` なので tool 併存の確認ダイアログ費用はない。

**ハイパーメディア**: MCP の `resource_link` content は HATEOAS リンクの部分実装。`todo_post` の結果に `{"type":"resource_link","uri":"app://self/todo?id=42"}` を含めればエージェントは resources/read で**遷移**できる。tools/call を「非安全な状態遷移」、resource_link を「次に辿れる safe な遷移」として提示する — HAL レンダリングの MCP 版であり、本パッケージ最大の差別化点。

---

## 2. 公開宣言 — `#[Mcp]` 属性(default-closed)

原則: **属性なし = 一切公開しない**。ToolUse の「URI 収集後は opt-out」モデルは MCP には採らない(URI を 1 つ載せた瞬間 `onDelete` まで露出する事故を構造的に排除。審査で pragmatist 案の最重度欠陥と判定された点)。`grep '#\[Mcp'` = 公開面の全リスト、という監査可能性を保証する。5–15 ツールに絞る業界合意とも整合。

```php
namespace BEAR\Mcp\Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class Mcp
{
    public function __construct(
        public string|null $name = null,        // 省略時: URI パス由来 "todo_get"(ToolUse と同一の命名規則)
        public string|null $title = null,
        public string|null $description = null, // 省略時: OPTIONS メタデータ(phpdoc summary)
        public Expose|null $as = null,          // 省略時 Auto: GET→Both / 他動詞→Tool
        public string|null $mimeType = null,    // resource 投影時。既定 application/json
        public bool|null $destructive = null,   // 動詞由来ヒントの上書き(例: 論理削除の DELETE)
        public bool|null $idempotent = null,
        public bool $openWorld = false,
    ) {}
}

enum Expose
{
    case Auto;      // GET: Resource+Tool / その他: Tool
    case Resource;  // GET 専用(他動詞に付けたらスキャナが例外 = fail-fast)
    case Tool;
    case Both;      // GET 専用
}

#[Attribute(Attribute::TARGET_METHOD)]
final class McpExclude {}   // クラスレベル #[Mcp] からの個別除外
```

- クラスレベル `#[Mcp]` = 全 `on*` メソッド公開(メソッドレベルが上書き、`#[McpExclude]` で除外)。優先順位規則は ToolUse と同じなので学習コストなし。
- ツール名導出アルゴリズムは ToolUse と**同一に保つ**(`str_replace(['/','-'],'_',trim($path,'/')) . '_' . $verb`)。同じリソースが agent loop でも MCP でも同名で見える。
- 属性を使えない場合(ベンダーリソース等)は `McpConfig` の `expose:` マップで宣言(こちらも default-closed)。両経路は `McpMapInterface` に正規化される。

**発見機構**: ブート時に `AbstractAppMeta::getResourceListGenerator()`(BEAR.Cli の `CompileScript` と同じ機構)で ResourceObject を列挙し `#[Mcp]` を収集、URI は `BEAR\Resource\Meta` で導出。結果は不変の `McpMap`。長命プロセスなので走査 1 回で償却。prod 向けに `var/tmp/{context}/mcp-map.php` へのコンパイルを optional 提供(`composer compile` フック)。

---

## 3. スキーマ導出 — 「tools/list とは公開部分集合に対する OPTIONS * である」

`inputSchema` は **`BEAR\Resource\OptionsMethods` を DI で注入して直接呼ぶ**。

- `OptionsMethods::__invoke($ro, 'get')` は summary / parameters(type, description, default, in)/ required / `#[JsonSchema]` ファイル内容 / `#[Input]` DTO 展開まで返す。**車輪は bear/resource 本体に既にある**。
- レンダラを経由しないため、**prod の `NullOptionsRenderer`(OPTIONS 405 化)の影響を受けない**。HTTP 面では OPTIONS を閉じたまま MCP 面でメタデータを使える。「自己記述は uniform interface の一部でありレンダリングと独立」という BEAR の層構造の正しさの証明。

優先順位(ToolUse の確立規則を踏襲):

1. `#[JsonSchema(params: 'todo-get.json')]` — `json_validate_dir` のファイルを `inputSchema` の骨格に。**入力バリデーション AOP と同一ファイル** = LLM に見せるスキーマと実際に強制されるスキーマが定義上一致。
2. `OptionsMethods` 由来(リフレクション型 + `@param`)。
3. ALPS semantic dictionary(bear/tool-use 併存時の optional 強化)。

`outputSchema`: `#[JsonSchema(schema:)]` の root が `object` の場合のみ設定し `structuredContent` を返す(spec の root-object 制約)。`params:` の enum は `completion/complete`(template 変数補完)にも再利用。

### 実行(tools/call)

```php
final class ResourceToolHandler // mcp/sdk RuntimeToolHandlerInterface に接続
{
    public function __construct(
        private ResourceInterface $resource,
        private ToolDescriptor $tool,   // uri, verb, inputSchema, annotations, linkRels
    ) {}

    public function getInputSchema(): array
    {
        return $this->tool->inputSchema;
    }

    public function __invoke(array $arguments): CallToolResult
    {
        try {
            $ro = $this->resource->{$this->tool->verb}($this->tool->uri, $arguments);
        } catch (BadRequestException $e) {
            return Result::error("400: {$e->getMessage()}. Check argument types against inputSchema.");
        } catch (Throwable $e) {
            return Result::error($e::class . ': ' . $e->getMessage());   // LLM 自己修正ループへ
        }
        if ($ro->code >= 400) {
            return Result::error("{$ro->code}: " . json_encode($ro->body, JSON_UNESCAPED_UNICODE));
        }

        return Result::success(
            text: json_encode($ro->body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            structured: $this->tool->outputSchema !== null && is_array($ro->body) ? $ro->body : null,
            links: $this->links($ro),    // #[Link] → resource_link(v0.2)
        );
    }
}
```

- エラー 2 系統は spec に忠実: 業務・バリデーションエラーは `isError: true`(protocol error にしない)、resources/read の不在のみ `-32002`。
- レンダラは経由せず `$ro->body` を直接使う(BEAR.Cli の `--format=json` と同じ理由)。
- 各 tools/call を `ob_start`/`ob_end_clean` で包み、ハンドラ内の echo/notice の stdout 漏れを呼び出し粒度でも防ぐ(ブート時ガードの補完)。

---

## 4. ランタイム — mcp/sdk ^0.6 の上、ただし隔離して

### SDK 選定(2026-07 時点の実地調査に基づく)

**公式 `mcp/sdk`(modelcontextprotocol/php-sdk、v0.6.0)一択**。

1. `StreamableHttpTransport` が **PSR-7 in → PSR-7 out の純関数**でリクエストループを所有しない(php-mcp/server は ReactPHP 常駐で 2025-08 から休眠、logiscape は superglobals 直読みで PSR-7 ゼロ)。
2. v0.6.0 の **Runtime Handler API**(`Builder::add()` + `RuntimeToolHandlerInterface` 等)は設定駆動のフレームワーク統合専用の口(Drupal 向けに追加)。リフレクションを SDK に渡さず `getInputSchema()` を自前供給できる — §3 の OptionsMethods 導出がそのまま接続する。
3. セッションは `Psr16SessionStore` / `FileSessionStore` 抽象。
4. spec 2025-11-25 対応。Drupal / API Platform / Nette / CakePHP が既に同 SDK 上に統合を構築済み。

**pre-1.0 対策**(BC break 実績あり: `Resource`→`ResourceDefinition` 改名):

- SDK 型は `src/Sdk/`(Handler / Transport)の内側に閉じ込め、`#[Mcp]`・`McpMap`・`ToolDescriptor` 等の公開 API には一切漏らさない。**`Sdk\` 名前空間 = BC break 吸収壁**。
- `tools/list` / `resources/templates/list` の**ゴールデンファイル(ワイヤスナップショット)テスト**で SDK 更新起因のワイヤ変化を検出。
- CI: mcp/sdk の dev ブランチ追従ジョブ + MCP 公式 conformance suite の週次実行。

### stdio(第一級トランスポート)

`vendor/bin/bear-mcp` を composer bin として出荷(BEAR.Cli の先例)。**アプリ側にファイル追加ゼロ**で起動できる:

```json
{ "mcpServers": { "my-app": {
    "command": "php",
    "args": ["/path/to/app/vendor/bin/bear-mcp", "MyVendor\\MyApp", "prod-mcp-app", "/path/to/app"]
} } }
```

`McpBootstrap`(bin の実体)の仕事:

1. **stdout 規律の確保**(spec MUST): `ini_set('display_errors', 'stderr')` + 出力バッファガード。PHP は notice 1 発でプロトコルが壊れるためパッケージ側で防護。
2. `BEAR\Package\Injector::getInstance($appName, $context, $appDir)`(prod はコンパイル済みインジェクタ復元)→ `ServerFactory` → `McpMap` から Runtime Handler 全登録 → `StdioTransport::run()`。
3. セッションストアは InMemory。

`ServerFactory` を**唯一の組み立て点**とし、マップ構築 → ハンドラ登録の順序依存をそこに閉じ込める。

### Streamable HTTP

PSR-15 ハンドラを出荷。bear/middleware や任意の PSR-15 スタック(FrankenPHP worker / RoadRunner / php-fpm)にマウント:

```php
final class McpRequestHandler implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $transport = new StreamableHttpTransport($request);  // Origin 検証 / DNS rebinding 防御込み
        return $this->provider->get()->run($transport);
    }
}
```

- セッションは `Psr16SessionStore` をアプリの PSR-16 キャッシュに束縛(`McpHttpModule`)。
- 「MCP エンドポイントを Page リソースにする」案は**採らない**: SSE ストリーミングボディは BEAR の Responder/transfer モデルと噛み合わず、PSR-7 応答をそのまま返すのが正直。
- worker モード並行時は `ResourceInterface` を stateless な `ResourceClient` に再束縛(`McpHttpModule` が実施。Swoole Module と同じ手筋)。stdio は逐次なので既定のままでよい。

### コンテキスト

アプリが `src/Module/McpModule.php` を定義し、コンテキスト文字列 `prod-mcp-app` で起動する。`BEAR\Package\Module` はアプリ名前空間の `McpModule` を解決するため、**フレームワーク変更ゼロ**でコンテキストセグメント `mcp` が成立。MCP の束縛が web/cli コンテキストへ一切漏れない。

---

## 5. パッケージ構成

```json
{
    "name": "bear/mcp",
    "description": "Serve a BEAR.Sunday application as an MCP server",
    "require": {
        "php": "^8.2",
        "ext-json": "*",
        "mcp/sdk": "^0.6",
        "bear/resource": "^1.10",
        "bear/app-meta": "^1.0",
        "bear/package": "^1.13",
        "ray/di": "^2.18",
        "psr/http-server-handler": "^1.0"
    },
    "require-dev": { "phpunit/phpunit": "^11.0" },
    "suggest": {
        "bear/tool-use": "Reuse #[Tool] descriptions and ALPS-based parameter docs for MCP"
    },
    "bin": ["bin/bear-mcp"],
    "autoload": { "psr-4": { "BEAR\\Mcp\\": "src/" } }
}
```

`bear/package` は **require に入れる**(BEAR.Cli の先例に忠実。`McpBootstrap` が `BEAR\Package\Injector` を使う以上、suggest に置くのは未宣言の実行時依存になる — 審査で検証済みの指摘)。

```
src/
├── Attribute/        Mcp.php  Expose.php  McpExclude.php
├── Map/              McpMapInterface.php  AttributeMcpMap.php  ConfigMcpMap.php
│                     ToolDescriptor.php  ResourceDescriptor.php  TemplateDescriptor.php
│                     AnnotationDeriver.php     # 動詞 → ToolAnnotations(§1 の表)
├── Schema/           InputSchemaFactory.php    # OptionsMethods + #[JsonSchema(params:)] マージ
│                     OutputSchemaFactory.php  UriTemplateFactory.php  # 引数 → {?a,b}
├── Sdk/              # ← mcp/sdk 型はこの名前空間から外に出さない(BC 吸収壁)
│   ├── Handler/      ResourceToolHandler.php  ResourceReadHandler.php  TemplateReadHandler.php
│   │                 LinkEmitter.php           # #[Link] → resource_link
│   └── Transport/    McpBootstrap.php  McpRequestHandler.php
├── Server/           ServerFactory.php  McpConfig.php
├── Interop/          ToolUseBridge.php         # §7
└── Module/           McpModule.php  McpHttpModule.php
docs/templates/       bin-mcp.php  public-mcp.php  McpModule.php   # コピペ用
```

`Map/` と `Schema/`(ToolDescriptor / AnnotationDeriver / 中立スキーマ形)はヘッド非依存に保ち、将来 ToolUse と共有する `bear/tool-meta` の**抽出線**として名前空間で予告する(投機的パッケージは今は出荷しない)。

### アプリからの利用(エンドツーエンド)

```php
class Todo extends ResourceObject
{
    /** Todo を ID で取得する */
    #[Mcp]                                    // → resource template app://self/todo{?id}
    #[JsonSchema(params: 'todo-get.json')]    //   + readOnly tool "todo_get"
    #[Link(rel: 'comments', href: 'app://self/todo/comments?todo_id={id}')]
    public function onGet(int $id): static { /* ... */ }

    /** Todo を作成する */
    #[Mcp]                                    // → tool "todo_post"
    public function onPost(string $title): static { /* ... */ }

    #[Mcp(destructive: false)]                // 論理削除なので上書き
    public function onDelete(int $id): static { /* ... */ }

    public function onPut(int $id, string $title): static { /* 属性なし = MCP に存在しない */ }
}
```

これと `src/Module/McpModule.php`(`McpConfig` の install)だけで、`tools/list` に `todo_get` / `todo_post` / `todo_delete` が正しい注釈付きで、`resources/templates/list` に `app://self/todo{?id}` が並ぶ。

### テスト戦略

1. **単体**: `AnnotationDeriver`(動詞→注釈のテーブル駆動)、`UriTemplateFactory`、`InputSchemaFactory`(マージ優先順位)。
2. **統合(実スタック・モックなし)**: `tests/Fake` の実リソースで `McpMap` 構築 → tools/list / resources/read の JSON を**ゴールデンファイル比較**。
3. **プロトコル E2E**: `proc_open` で Fake アプリの stdio サーバーを子プロセス起動し、`initialize` → `tools/list` → `tools/call` を実往復(stdout 汚染の回帰検知を兼ねる)。
4. CI: PHP 8.2–8.5 matrix、mcp/sdk dev 追従ジョブ、MCP 公式 conformance suite 週次。カバレッジ 100% 方針(エコシステム標準)。

---

## 6. BEAR.ToolUse との関係 — 属性は分け、意味論を共有し、スキーマ層で収斂する

**`#[Tool]` は再利用しない**。理由:

1. **公開モデルが逆**: ToolUse は収集 URI 内 opt-out(`#[Exclude]`)、MCP は default-closed の opt-in(セキュリティ要件)。同じ属性に両義性を持たせるのは事故のもと。
2. **語彙が違う**: `confirm` / `filter` はアプリ内エージェントループの語彙で MCP に対応概念がない。MCP 側は `mimeType` / `Expose` / annotation 上書きが要る。
3. **層が違う**: ToolUse は「アプリが LLM を使う」(会話ループ所有)、Mcp は「LLM がアプリを使う」(プロトコル公開)。

その上で 3 段階の連携:

- **v0(出荷時)— `Interop\ToolUseBridge`**(`class_exists` によるソフト依存、suggest のみ):
  `#[Tool]` 注釈済みアプリの `ToolCollector::collect()` 結果を MCP tool に**併載**できる。正規化 3 点セット + confirm 翻訳:
  1. `input_schema` → `inputSchema`(`jsonSerialize()` ではなく `Schema\Tool` のオブジェクトプロパティから組む)
  2. 非標準 `nullable: true` → `type: ["T", "null"]`(JSON Schema 2020-12 準拠化)
  3. 独自キー `confirm` をワイヤから除去し **`destructiveHint: true` へ翻訳**(MCP に confirm 概念はなく、クライアントの承認 UI は annotations 経由で引き出すのが正しい写像)
  実行は ToolUse の `Dispatcher` に委譲するオプションも提供 — エラーフィードバックループ・result filter・`ToolCallObserverInterface`(監査/メトリクス)が無料で付いてくる。
- **v1 — ALPS 共有**: ToolUse の `AlpsSemanticDictionary` を optional 注入し、パラメータ description と completion 候補を強化(優先順位 JsonSchema > PHPDoc > ALPS は両パッケージ同一規則)。
- **v1.x — 上流収斂の提案**: ToolUse の `SchemaConverter` は OptionsMethods が存在するのに純リフレクションを再実装している。**「ResourceObject の自己記述 → JSON Schema」を bear/resource(OptionsMethods)に一本化し、ToolUse・Mcp 双方が消費する**構図を bearsunday org に提案。併せて ToolUse への上流 PR 候補: `Schema\Tool` プロパティの安定 API 宣言、`collect()` と Registry 登録の分離(純関数化)、カスタム `#[Tool(name:)]` の動詞推測 `get` フォールバックの是正。

---

## 7. リスクと検証スパイク

| # | リスク | 深刻度 | 対応 |
|---|---|---|---|
| 1 | **HTTP トランスポートの認証**(mcp/sdk の OAuth 2.1 RS 対応は未完) | 高 | v1 は stdio 第一級。HTTP は「信頼境界内(前段 PSR-15 ミドルウェアで Bearer 検証)」と明記。OAuth は SDK 追従で v2 |
| 2 | **単一プリンシパル問題**(tools はアプリ権限で実行、「誰として」がない。Ray.Di にセッションスコープなし) | 高 | v1 のターゲットを「開発者の stdio」「サービスアカウント的 HTTP」と明示。per-request 可視性フックは v2 検討 |
| 3 | **⚡実装初日の検証スパイク**: mcp/sdk のテンプレートマッチャが RFC 6570 form-style `{?id}` を解釈できるか未検証(確証があるのは `{var}` のみ。symfony/mcp-bundle はテンプレート非機能で出荷した実績あり) | 中 | 不可なら自前マッチャを Runtime Handler 側に持ち(構造上可能)、同時に上流へ PR。GET は tool にも併載されるため、失敗しても機能は残る(graceful degradation) |
| 4 | 長命プロセスの状態(既定 `Resource` クライアントは可変状態) | 中 | stdio(逐次)は問題なし。HTTP worker は `McpHttpModule` が stateless クライアントへ再束縛 |
| 5 | mcp/sdk pre-1.0 BC break | 中 | `^0.6` ピン + `Sdk\` 隔離 + ゴールデンワイヤテスト + dev 追従 CI |
| 6 | stdout 汚染 = プロトコル破壊 | 中 | ブート時 stderr 迂回 + per-call ob ガード + E2E 回帰テスト |
| 7 | ストリーミング/進捗(BEAR のリソース呼び出しは同期、SDK も standalone SSE 未実装) | 低 | progress / subscribe / listChanged を**宣言しない**(静的マップに listChanged は不要が正しい姿勢) |
| 8 | 巨大 tool 結果による LLM 文脈破壊 | 低 | `limit` パラメータ推奨をドキュメント化。ToolUse の filter 概念の輸入は v2 |
| 9 | `json_validate_dir` 未束縛アプリでの unbound(OptionsMethods の要求) | 低 | `McpModule` がデフォルト `''` を束縛 |
| 10 | prompts の空白(BEAR に対応概念なし) | 低 | 捏造しない。`#[McpPrompt]` を名前予約のみ |

補足(調査で確認済み): BEAR.Sunday + MCP の既存統合は Packagist / GitHub / Web 検索のいずれにも**存在しない**(2026-07 時点)。本パッケージが最初になる。

---

## 8. ロードマップ

1. **スパイク(着手前)**: リスク#3 — mcp/sdk の RFC 6570 form-style マッチング検証。
2. **v0.1**: `#[Mcp]` + `McpMap` + `InputSchemaFactory` + stdio(`vendor/bin/bear-mcp`)+ tools のみ。ゴールデンテスト + E2E。*最小で動くものを最初に出す。*
3. **v0.2**: resources / resource templates(GET 二重投影)+ completion + `resource_link`(`#[Link]`)+ `Interop\ToolUseBridge`。
4. **v0.3**: Streamable HTTP(PSR-15 + `Psr16SessionStore` + `McpHttpModule`)+ mcp-map コンパイル。
5. **v1.0**: bearsunday org への移管提案 + OptionsMethods 収斂の上流 RFC。
