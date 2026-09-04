<?php
declare(strict_types=1);
session_start();
require __DIR__ . '/config.php';

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

$contentFile = dirname(__DIR__) . '/content.json';
$uploadDir = dirname(__DIR__) . '/assets/uploads';
$message = '';
$error = '';

function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function load_content(string $file): array {
    if (!is_file($file)) return [];
    $data = json_decode((string)file_get_contents($file), true);
    return is_array($data) ? $data : [];
}
function clean_phone_href(string $v): string {
    $v = preg_replace('/[^\d+]/u', '', $v) ?? '';
    if ($v !== '' && $v[0] !== '+') $v = '+' . $v;
    return $v;
}
function login_ok(string $password, string $salt, string $expected): bool {
    return hash_equals($expected, hash('sha256', $salt . $password));
}

if (isset($_GET['logout'])) {
    $_SESSION = [];
    session_destroy();
    header('Location: ./');
    exit;
}

if (!isset($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(24));

if (!($_SESSION['admin'] ?? false) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_password'])) {
    if (login_ok((string)$_POST['login_password'], $ADMIN_SALT, $ADMIN_PASSWORD_HASH)) {
        session_regenerate_id(true);
        $_SESSION['admin'] = true;
        header('Location: ./');
        exit;
    }
    $error = 'Неверный пароль';
}

if (!($_SESSION['admin'] ?? false)) {
?><!doctype html>
<html lang="ru"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>PADEL ALTAY — вход</title>
<style>
*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;background:#f5f6f2;color:#11191b;font-family:Inter,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;padding:20px}
.card{width:min(430px,100%);background:#fff;border:1px solid #e4e8df;border-radius:24px;padding:30px;box-shadow:0 24px 70px rgba(30,45,40,.12)}
.logo{width:52px;height:52px;border-radius:15px;background:#c9f31d;display:grid;place-items:center;font-weight:950;margin-bottom:22px}
h1{margin:0 0 8px;font-size:28px}p{color:#6e7778;line-height:1.5;margin:0 0 22px}
input{width:100%;border:1px solid #dfe4df;border-radius:12px;padding:14px;font:inherit;outline:0}input:focus{border-color:#9fbe14;box-shadow:0 0 0 3px rgba(201,243,29,.18)}
button{width:100%;border:0;background:#c9f31d;color:#111;border-radius:12px;padding:14px;margin-top:12px;font-weight:900;font-size:14px;cursor:pointer}
.err{background:#fff0f0;color:#a72828;border-radius:10px;padding:10px 12px;font-size:12px;margin-bottom:13px}.note{font-size:11px;color:#92999a;margin-top:16px}
</style></head><body><form class="card" method="post">
<div class="logo">П</div><h1>Управление сайтом</h1><p>ПАДЕЛ · «Империя туризма»</p>
<?php if ($error): ?><div class="err"><?=e($error)?></div><?php endif; ?>
<input type="password" name="login_password" placeholder="Пароль" autocomplete="current-password" autofocus required>
<button type="submit">Войти</button>
<div class="note">Тестовая админка. Перед передачей клиенту пароль будет заменён.</div>
</form></body></html><?php
exit;
}

$content = load_content($contentFile);
$content += ['hero'=>[], 'pricing'=>[], 'contacts'=>[]];
$content['hero'] += ['eyebrow'=>'','title'=>'','subtitle'=>'','description'=>'','image'=>''];
$content['pricing'] += ['hours'=>'','courtPrice'=>0,'racketPrice'=>0,'players'=>''];
$content['contacts'] += ['phone'=>'','phoneHref'=>'','address'=>''];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_content'])) {
    if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) {
        $error = 'Сессия устарела. Обновите страницу.';
    } else {
        $new = $content;
        $new['hero']['eyebrow'] = trim((string)($_POST['hero_eyebrow'] ?? ''));
        $new['hero']['title'] = trim((string)($_POST['hero_title'] ?? ''));
        $new['hero']['subtitle'] = trim((string)($_POST['hero_subtitle'] ?? ''));
        $new['hero']['description'] = trim((string)($_POST['hero_description'] ?? ''));
        $new['pricing']['hours'] = trim((string)($_POST['hours'] ?? ''));
        $new['pricing']['courtPrice'] = max(0, (int)($_POST['court_price'] ?? 0));
        $new['pricing']['racketPrice'] = max(0, (int)($_POST['racket_price'] ?? 0));
        $new['pricing']['players'] = trim((string)($_POST['players'] ?? ''));
        $new['contacts']['phone'] = trim((string)($_POST['phone'] ?? ''));
        $new['contacts']['phoneHref'] = clean_phone_href((string)($_POST['phone_href'] ?? ''));
        $new['contacts']['address'] = trim((string)($_POST['address'] ?? ''));

        if (isset($_FILES['hero_image']) && ($_FILES['hero_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $f = $_FILES['hero_image'];
            if ($f['error'] !== UPLOAD_ERR_OK) {
                $error = 'Не удалось загрузить фотографию.';
            } elseif ($f['size'] > $MAX_UPLOAD_BYTES) {
                $error = 'Фото слишком большое. Максимум 5 МБ.';
            } else {
                $info = @getimagesize($f['tmp_name']);
                $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
                $mime = $info['mime'] ?? '';
                if (!isset($allowed[$mime])) {
                    $error = 'Поддерживаются JPG, PNG и WebP.';
                } else {
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                    $filename = 'hero-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.' . $allowed[$mime];
                    $target = $uploadDir . '/' . $filename;
                    if (!move_uploaded_file($f['tmp_name'], $target)) {
                        $error = 'Сервер не смог сохранить фотографию.';
                    } else {
                        $new['hero']['image'] = 'assets/uploads/' . $filename;
                    }
                }
            }
        }

        if ($error === '') {
            $json = json_encode($new, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            if ($json === false || file_put_contents($contentFile, $json . PHP_EOL, LOCK_EX) === false) {
                $error = 'Не удалось сохранить content.json. Проверьте права на файл.';
            } else {
                $content = $new;
                $message = 'Изменения сохранены. Обновите сайт — они уже опубликованы.';
            }
        }
    }
}
?><!doctype html>
<html lang="ru"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>Падел — админка</title>
<style>
:root{--bg:#f4f6f2;--card:#fff;--ink:#11191b;--muted:#758080;--line:#dfe5de;--lime:#c9f31d;--shadow:0 18px 50px rgba(30,45,40,.07);--r:19px}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font-family:Inter,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
.wrap{width:min(1080px,calc(100% - 30px));margin:auto;padding:25px 0 60px}.top{display:flex;align-items:center;gap:13px;margin-bottom:22px}.mark{width:48px;height:48px;border-radius:14px;background:var(--lime);display:grid;place-items:center;font-weight:950}.top h1{font-size:23px;margin:0}.top small{color:var(--muted)}
.actions{margin-left:auto;display:flex;gap:8px}.btn{display:inline-flex;align-items:center;justify-content:center;border:1px solid var(--line);background:#fff;color:#111;text-decoration:none;border-radius:11px;padding:11px 14px;font-weight:800;font-size:12px}.btn.dark{background:#111;color:#fff;border-color:#111}
.notice{padding:12px 15px;border-radius:12px;margin-bottom:16px;font-size:13px}.ok{background:#efffc1;color:#425000}.err{background:#fff0f0;color:#a42b2b}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}.card{background:#fff;border:1px solid var(--line);border-radius:var(--r);padding:21px;box-shadow:var(--shadow)}.wide{grid-column:1/-1}
.card h2{font-size:18px;margin:0 0 18px}.field{margin-bottom:16px}.field:last-child{margin-bottom:0}label{display:block;font-size:11px;font-weight:850;margin-bottom:7px;text-transform:uppercase;letter-spacing:.04em}
input,textarea{width:100%;border:1px solid var(--line);background:#fbfcfa;border-radius:11px;padding:12px 13px;font:inherit;font-size:14px;color:var(--ink);outline:0}textarea{min-height:105px;resize:vertical}input:focus,textarea:focus{border-color:#9fbe14;box-shadow:0 0 0 3px rgba(201,243,29,.16)}
.cols{display:grid;grid-template-columns:1fr 1fr;gap:11px}.heroimg{display:grid;grid-template-columns:180px 1fr;gap:15px;align-items:center}.preview{height:120px;background:#eef1ec;border:1px solid var(--line);border-radius:13px;overflow:hidden;display:grid;place-items:center;color:#88908f;font-size:12px}.preview img{width:100%;height:100%;object-fit:cover}.hint{font-size:11px;color:var(--muted);line-height:1.45;margin-top:6px}
.savebar{position:sticky;bottom:14px;display:flex;justify-content:flex-end;margin-top:18px}.save{border:0;background:var(--lime);color:#10140b;border-radius:13px;padding:15px 24px;font-size:13px;font-weight:950;box-shadow:0 10px 26px rgba(143,171,17,.23);cursor:pointer}
@media(max-width:720px){.wrap{width:min(100% - 20px,600px);padding-top:12px}.top{align-items:flex-start;flex-wrap:wrap}.actions{width:100%;margin-left:0}.actions .btn{flex:1}.grid{grid-template-columns:1fr}.wide{grid-column:auto}.cols{grid-template-columns:1fr}.heroimg{grid-template-columns:1fr}.preview{height:190px}.card{padding:17px}.savebar .save{width:100%}}
</style></head><body>
<div class="wrap">
<div class="top"><div class="mark">П</div><div><h1>ПАДЕЛ · АЛТАЙ</h1><small>Админка сайта</small></div>
<div class="actions"><a class="btn" href="../" target="_blank">Открыть сайт ↗</a><a class="btn dark" href="?logout=1">Выйти</a></div></div>

<?php if ($message): ?><div class="notice ok"><?=e($message)?></div><?php endif; ?>
<?php if ($error): ?><div class="notice err"><?=e($error)?></div><?php endif; ?>

<form method="post" enctype="multipart/form-data">
<input type="hidden" name="csrf" value="<?=e($_SESSION['csrf'])?>">
<input type="hidden" name="save_content" value="1">
<div class="grid">
<section class="card wide"><h2>Главный экран</h2>
<div class="cols"><div class="field"><label>Надзаголовок</label><input name="hero_eyebrow" value="<?=e((string)$content['hero']['eyebrow'])?>"></div>
<div class="field"><label>Главный заголовок</label><input name="hero_title" value="<?=e((string)$content['hero']['title'])?>"></div></div>
<div class="field"><label>Вторая строка</label><input name="hero_subtitle" value="<?=e((string)$content['hero']['subtitle'])?>"></div>
<div class="field"><label>Описание</label><textarea name="hero_description"><?=e((string)$content['hero']['description'])?></textarea></div>
<div class="field"><label>Новое фото первого экрана</label><div class="heroimg">
<div class="preview"><?php if ($content['hero']['image']): ?><img src="../<?=e((string)$content['hero']['image'])?>?v=<?=time()?>"><?php else: ?>Сейчас используется фото из дизайна<?php endif; ?></div>
<div><input type="file" name="hero_image" accept="image/jpeg,image/png,image/webp"><div class="hint">JPG / PNG / WebP, максимум 5 МБ. Если ничего не выбирать, текущее фото останется.</div></div></div></div>
</section>

<section class="card"><h2>Цены и режим</h2>
<div class="field"><label>Корт / час, ₽</label><input type="number" min="0" name="court_price" value="<?=e((string)$content['pricing']['courtPrice'])?>"></div>
<div class="field"><label>Ракетка / час, ₽</label><input type="number" min="0" name="racket_price" value="<?=e((string)$content['pricing']['racketPrice'])?>"></div>
<div class="cols"><div class="field"><label>Режим</label><input name="hours" value="<?=e((string)$content['pricing']['hours'])?>"></div>
<div class="field"><label>Игроки</label><input name="players" value="<?=e((string)$content['pricing']['players'])?>"></div></div>
</section>

<section class="card"><h2>Контакты</h2>
<div class="field"><label>Телефон на сайте</label><input name="phone" value="<?=e((string)$content['contacts']['phone'])?>"></div>
<div class="field"><label>Телефон для кнопки звонка</label><input name="phone_href" value="<?=e((string)$content['contacts']['phoneHref'])?>"><div class="hint">Например: +79039120022</div></div>
<div class="field"><label>Локация / адрес</label><input name="address" value="<?=e((string)$content['contacts']['address'])?>"></div>
</section>
</div>
<div class="savebar"><button class="save" type="submit">Сохранить изменения</button></div>
</form>
</div></body></html>