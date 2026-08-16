# Unraid Docker Template XML Format Reference

Templates live in `/boot/config/plugins/dockerMan/templates-user/` and appear in Community Applications → Installed Apps.

## Minimal Template

```xml
<?xml version="1.0"?>
<Container version="2">
  <Name>MyApp</Name>
  <Repository>ghcr.io/user/myapp:latest</Repository>
</Container>
```

## Full Template With All Fields

```xml
<?xml version="1.0"?>
<Container version="2">
  <!-- BASIC INFO -->
  <Name>OmniOperator</Name>
  <Repository>ghcr.io/TheNoticingBegins/omni-operator:latest</Repository>
  <Registry>https://github.com/TheNoticingBegins/omni-operator</Registry>
  <Support>https://github.com/TheNoticingBegins/omni-operator/issues</Support>
  <Overview>Multi-capability Telegram bot</Overview>
  <Category>Productivity:Tools:</Category>
  <Requires>BOT_TOKEN</Requires>

  <!-- NETWORKING -->
  <Networking>
    <Mode>bridge</Mode>  <!-- bridge, host, or none (omit for default bridge) -->
  </Networking>

  <!-- PORT MAPPINGS (one per port) -->
  <Config Name="Web UI" Target="8080" Default="8080" Mode="tcp" Description="Web interface" Type="Port" />
  <Config Name="Metrics" Target="9090" Default="9090" Mode="tcp" Description="Prometheus metrics" Type="Port" />

  <!-- VOLUME MOUNTS (one per mount) -->
  <Config Name="Config" Target="/config" Default="/mnt/user/appdata/myapp/config" Mode="rw" Description="Configuration files" Type="Path" />
  <Config Name="Data" Target="/data" Default="/mnt/user/appdata/myapp/data" Mode="rw" Description="Persistent data" Type="Path" />
  <Config Name="Cache" Target="/cache" Default="/mnt/user/appdata/myapp/cache" Mode="rw" Description="App cache" Type="Path" />

  <!-- ENVIRONMENT VARIABLES (one per variable) -->
  <Environment name="BOT_TOKEN" label="Bot Token" mode="s" description="Telegram bot token" default="" required="true" />
  <Environment name="AUTHORIZED_USERS" label="Authorized Users" mode="s" description="Comma-separated user IDs" default="" required="true" />
  <Environment name="ASR_MODEL" label="Whisper Model" mode="s" description="faster-whisper model" default="distil-large-v3" required="false" />
  <Environment name="LOG_LEVEL" label="Log Level" mode="s" description="" default="INFO" required="false" />

  <!-- LABELS -->
  <Label name="com.docker.compose.project" value="myapp" />
</Container>
```

## Field Reference

### `<Container>` Attributes

| Attribute | Value | Description |
|---|---|---|
| `version` | `2` | **Required.** Always `"2"` |

### Basic Info

| Element | Required | Description |
|---|---|---|
| `Name` | ✅ | Container name — appears in the template list |
| `Repository` | ✅ | Docker image (e.g. `ghcr.io/user/repo:tag`) |
| `Registry` | — | Link to the project's source repo |
| `Support` | — | Link for support / issues |
| `Overview` | — | Short description shown in App details |
| `Category` | — | Colon-separated path e.g. `Productivity:Tools:` |
| `Requires` | — | Warnings shown before install (e.g. "Requires BOT_TOKEN") |

### Networking

| Element | Attributes | Description |
|---|---|---|
| `Networking` | — | Wrapper for network settings |
| `Mode` | — | `bridge` (default), `host`, or `none` |

### Config (Ports, Volumes, and Custom Fields)

Each `<Config>` entry becomes a row in the Docker add/edit container form:

| Attribute | Required | Description |
|---|---|---|
| `Name` | — | Label shown in the UI |
| `Target` | ✅ | Container-side port/path |
| `Default` | — | Default value (host port or host path) |
| `Mode` | — | `tcp`, `udp`, `rw`, `ro` |
| `Description` | — | Help tooltip |
| `Type` | ✅ | `Port` (port mapping), `Path` (volume mount), or `WebUI` |

### Environment Variables

Each `<Environment>` entry becomes a field in the container env vars section:

| Attribute | Required | Description |
|---|---|---|
| `name` | ✅ | Variable name (e.g. `BOT_TOKEN`) |
| `label` | — | Human-readable label shown in UI |
| `mode` | — | `s` (string), `p` (password), `b` (boolean), `n` (number) |
| `description` | — | Help text |
| `default` | — | Default value |
| `required` | — | `"true"` or `"false"` |

### Labels

| Element | Attributes | Description |
|---|---|---|
| `Label` | `name`, `value` | Docker label (optional metadata) |

## Validation

The **Verify Templates** button (orange) in the Docker Builder plugin checks for:

- Valid XML syntax
- Required `Name` and `Repository` fields
- Valid repository format (no spaces, valid chars)
- `version="2"` on `<Container>`
- Config entries have `Target` and `Type`
- Port defaults are numeric
- Env var names are valid `KEY` format
- Required env vars have defaults
- Warning if template has no Config/Environment entries