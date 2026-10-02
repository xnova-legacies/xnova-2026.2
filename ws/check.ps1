# Verification du canal temps reel (service ws).
#
#   .\ws\check.ps1                 # hote et ports par defaut (localhost:8080 / 8085)
#   .\ws\check.ps1 -WsPort 9001    # autre port WebSocket
#
# Le script se connecte au jeu, ouvre le canal avec le cookie de session, envoie
# quelques requetes et affiche les reponses et les evenements pousses.

param(
    [string]$AppUrl = 'http://localhost:8080',
    [string]$WsHost = 'localhost',
    [int]$WsPort = 8085,
    [string]$Username = 'admin',
    [string]$Password = 'admin123',
    [int]$ListenMs = 8000
)

$ErrorActionPreference = 'Stop'

Write-Host "1. Connexion a $AppUrl"
$session = New-Object Microsoft.PowerShell.Commands.WebRequestSession
Invoke-WebRequest -Uri "$AppUrl/front/login" -WebSession $session -UseBasicParsing | Out-Null
Invoke-WebRequest -Uri "$AppUrl/front/login" -Method POST -WebSession $session -UseBasicParsing `
    -Body @{ username = $Username; password = $Password } | Out-Null

$page = Invoke-WebRequest -Uri "$AppUrl/game/overview" -WebSession $session -UseBasicParsing
$token = [regex]::Match($page.Content, 'content="([0-9a-f]{64})"').Groups[1].Value

if (-not $token) {
    throw "Jeton CSRF introuvable : la connexion a echoue."
}

$cookie = $session.Cookies.GetCookies($AppUrl) | Select-Object -First 1
Write-Host "   session : $($cookie.Name)"

function New-Channel {
    param([System.Net.Cookie] $Cookie)

    $socket = New-Object System.Net.WebSockets.ClientWebSocket

    if ($Cookie) {
        $container = New-Object System.Net.CookieContainer
        $null = $container.Add($Cookie)
        $socket.Options.Cookies = $container
    }

    $null = $socket.ConnectAsync([Uri]"ws://${WsHost}:${WsPort}/", [Threading.CancellationToken]::None).GetAwaiter().GetResult()

    # PowerShell 5.1 peut laisser filtrer d'autres objets : on ne renvoie que le
    # ClientWebSocket, sinon l'appelant recoit un tableau.
    return $socket
}

function Get-Socket {
    param($Value)

    return @($Value) | Where-Object { $_ -is [System.Net.WebSockets.ClientWebSocket] } | Select-Object -First 1
}

function Send-Frame {
    param([System.Net.WebSockets.ClientWebSocket] $Socket, [string] $Payload)

    $bytes = [Text.Encoding]::UTF8.GetBytes($Payload)
    $segment = New-Object 'System.ArraySegment[byte]' -ArgumentList @(, $bytes)
    $null = $Socket.SendAsync($segment, [System.Net.WebSockets.WebSocketMessageType]::Text, $true, [Threading.CancellationToken]::None).GetAwaiter().GetResult()
}

function Receive-Frame {
    param([System.Net.WebSockets.ClientWebSocket] $Socket, [int] $TimeoutMs = 4000)

    $buffer = New-Object byte[] 262144
    $segment = New-Object 'System.ArraySegment[byte]' -ArgumentList @(, $buffer)

    try {
        $task = $Socket.ReceiveAsync($segment, [Threading.CancellationToken]::None)

        if (-not $task.Wait($TimeoutMs)) {
            return $null
        }

        $result = $task.Result
    } catch {
        return '<ferme>'
    }

    if ($result.MessageType -eq [System.Net.WebSockets.WebSocketMessageType]::Close) {
        return '<ferme>'
    }

    return [Text.Encoding]::UTF8.GetString($buffer, 0, $result.Count)
}

Write-Host "2. Sans session : la poignee de main doit etre refusee"
try {
    $anonymous = Get-Socket (New-Channel -Cookie $null)
    $answer = Receive-Frame -Socket $anonymous -TimeoutMs 3000
    Write-Host "   refus recu : $answer"
    $anonymous.Dispose()
} catch {
    Write-Host "   connexion rejetee par le serveur (comportement attendu)"
}

Write-Host "3. Avec session : ouverture du canal"
$ws = Get-Socket (New-Channel -Cookie $cookie)
Write-Host "   etat : $($ws.State)"

if ($null -eq $ws -or $ws.State -ne [System.Net.WebSockets.WebSocketState]::Open) {
    throw 'Le canal ne s''est pas ouvert.'
}

Send-Frame -Socket $ws -Payload (@{ id = 1; action = 'hello'; token = $token } | ConvertTo-Json -Compress)
Send-Frame -Socket $ws -Payload (@{ id = 2; action = 'get'; path = '/game/api/state' } | ConvertTo-Json -Compress)
Send-Frame -Socket $ws -Payload (@{ id = 3; action = 'post'; path = '/game/api/fleet/estimate'; payload = @{ ships = @{ '202' = 1 }; galaxy = 1; system = 1; planet = 4; planet_type = 1 } } | ConvertTo-Json -Compress)
Send-Frame -Socket $ws -Payload (@{ id = 4; action = 'get'; path = '/config.php' } | ConvertTo-Json -Compress)

Write-Host "4. Reponses (dont un chemin interdit et le relais d'une action)"
for ($i = 0; $i -lt 4; $i++) {
    $answer = Receive-Frame -Socket $ws

    if ($null -eq $answer) {
        Write-Host "   (pas de reponse dans le delai)"
        break
    }

    if ($answer.Length -gt 220) {
        $answer = $answer.Substring(0, 220) + '...'
    }

    Write-Host "   $answer"
}

Write-Host "5. Evenements pousses pendant $ListenMs ms (changement d'etat)"
# Une seule lecture longue : abandonner une reception en attente (timeout court)
# rendrait la connexion inutilisable pour la suite.
$answer = Receive-Frame -Socket $ws -TimeoutMs $ListenMs
$events = 0

if ($null -ne $answer -and $answer -like '*"event"*') {
    $events++
    $short = $answer
    if ($short.Length -gt 160) { $short = $short.Substring(0, 160) + '...' }
    Write-Host "   $short"
}

Write-Host "   -> $events evenement(s)"
$ws.Dispose()

Write-Host '6. Diffusion vers une autre connexion (ecriture depuis un second canal)'
$listener = Get-Socket (New-Channel -Cookie $cookie)
Send-Frame -Socket $listener -Payload (@{ id = 1; action = 'hello'; token = $token } | ConvertTo-Json -Compress)
$null = Receive-Frame -Socket $listener -TimeoutMs 5000

$writer = Get-Socket (New-Channel -Cookie $cookie)
Send-Frame -Socket $writer -Payload (@{ id = 1; action = 'hello'; token = $token } | ConvertTo-Json -Compress)
$null = Receive-Frame -Socket $writer -TimeoutMs 5000
Send-Frame -Socket $writer -Payload (@{ id = 2; action = 'post'; path = '/game/api/buildings/add'; payload = @{ element = 1 } } | ConvertTo-Json -Compress)
$reply = Receive-Frame -Socket $writer -TimeoutMs 6000

if ($reply) {
    $short = $reply
    if ($short.Length -gt 180) { $short = $short.Substring(0, 180) + '...' }
    Write-Host "   ecriture relayee : $short"
}

# Une seule lecture longue : la diffusion arrive au tick suivant (WS_POLL_MS).
$frame = Receive-Frame -Socket $listener -TimeoutMs 12000
$pushed = 0

if ($null -ne $frame -and $frame -like '*"event"*') {
    $pushed++
    $short = $frame
    if ($short.Length -gt 180) { $short = $short.Substring(0, 180) + '...' }
    Write-Host "   diffusion recue : $short"
}

Write-Host "   -> $pushed diffusion(s) recue(s) par la premiere connexion"
$listener.Dispose()
$writer.Dispose()
Write-Host 'Verification terminee.'
