#!/usr/bin/env bash
set -e

cd /workspaces/blifehealthy-core

# 1) ไปที่ main ล่าสุด
git checkout main
git pull --rebase

# 2) สร้าง/สลับ branch งาน
git checkout -b feat/wap-register-referral 2>/dev/null || git checkout feat/wap-register-referral

# 3) apply patch จาก Codex
(cd "$(git rev-parse --show-toplevel)" && git apply --3way <<'EOF'
diff --git a/public/shop/account.html b/public/shop/account.html
index bd3d71c365d8705d6ea7bf235a9466ef84a1c81c..4f8ff2ff218a7fe42bd5575e2c895c8280325baa 100644
--- a/public/shop/account.html
+++ b/public/shop/account.html
@@ -1,9 +1,21 @@
 <!doctype html><html><head><meta charset='utf-8'><meta name='viewport' content='width=device-width,initial-scale=1'><title>Account</title><link rel='stylesheet' href='assets/shop.css'></head><body>
-<div class='app'><div id='header'></div><div class='card'><h3>Login</h3><input id='phone' class='input' placeholder='phone'><input id='password' class='input' type='password' placeholder='password'><button id='loginBtn' class='btn btn-primary'>Login</button></div><div class='card'><h3>Register</h3><input id='name' class='input' placeholder='name'><input id='rphone' class='input' placeholder='phone'><input id='rpass' class='input' type='password' placeholder='password'><button id='regBtn' class='btn btn-ghost'>Register</button></div><div class='card'><button id='meBtn' class='btn btn-ghost'>Load /api/me</button><button id='logoutBtn' class='btn'>Logout</button><pre id='meBox' class='muted'></pre></div></div><div id='toast' class='toast'></div><div id='bottomNav'></div>
+<div class='app'><div id='header'></div>
+  <div class='card'>
+    <h3>Login</h3>
+    <input id='phone' class='input' placeholder='phone'>
+    <input id='password' class='input' type='password' placeholder='password' style='margin-top:8px'>
+    <button id='loginBtn' class='btn btn-primary' style='margin-top:8px'>Login</button>
+  </div>
+  <div class='card'>
+    <h3>สมัครสมาชิกใหม่</h3>
+    <p class='muted'>ไปยังหน้าสมัครสมาชิกที่รองรับโค้ดผู้แนะนำ</p>
+    <a href='register.html' class='btn btn-ghost' style='display:inline-block;text-decoration:none'>ไปหน้า Register</a>
+  </div>
+  <div class='card'><button id='meBtn' class='btn btn-ghost'>Load /api/me</button><button id='logoutBtn' class='btn'>Logout</button><pre id='meBox' class='muted'></pre></div>
+</div><div id='toast' class='toast'></div><div id='bottomNav'></div>
 <script src='assets/api.js'></script><script src='assets/app.js'></script><script>
-renderHeader('Account');renderBottomNav('account');loginBtn.onclick=login;regBtn.onclick=register;meBtn.onclick=me;logoutBtn.onclick=logout;
+renderHeader('Account');renderBottomNav('account');loginBtn.onclick=login;meBtn.onclick=me;logoutBtn.onclick=logout;
 async function login(){try{const d=await api.post('/api/auth/login',{phone:phone.value,password:password.value});shopSettings.setToken(d.token);showToast('login success','success')}catch(e){showToast(e.message,'error')}}
-async function register(){try{const d=await api.post('/api/auth/register',{name:name.value,phone:rphone.value,password:rpass.value});if(d.token)shopSettings.setToken(d.token);showToast('register success','success')}catch(e){showToast(e.message,'error')}}
 async function me(){try{meBox.textContent=JSON.stringify(await api.get('/api/me'),null,2)}catch(e){showToast(e.message,'error')}}
 async function logout(){try{await api.post('/api/auth/logout',{});shopSettings.setToken('');showToast('logout success','success')}catch(e){showToast(e.message,'error')}}
 </script></body></html>
diff --git a/public/shop/register.html b/public/shop/register.html
new file mode 100644
index 0000000000000000000000000000000000000000..b0638d5b6b8012a671de5871450074da923d6c29
--- /dev/null
+++ b/public/shop/register.html
@@ -0,0 +1,177 @@
+<!doctype html><html><head><meta charset='utf-8'><meta name='viewport' content='width=device-width,initial-scale=1'><title>Register</title><link rel='stylesheet' href='assets/shop.css'></head><body>
+<div class='app' id='body'>
+  <div id='header'></div>
+
+  <div class='card'>
+    <h3 style='margin-top:0'>สมัครสมาชิก</h3>
+    <div class='muted'>รองรับลิงก์แนะนำ: <code>?ref=TH0000001</code></div>
+  </div>
+
+  <div class='card'>
+    <h3 style='margin-top:0'>ข้อมูลผู้แนะนำ</h3>
+    <label class='muted' for='refCode'>รหัสผู้แนะนำ</label>
+    <input id='refCode' class='input' maxlength='9' placeholder='TH0000001'>
+    <div class='row' style='margin-top:10px'>
+      <button id='validateRefBtn' class='btn btn-ghost' type='button'>ตรวจสอบรหัสแนะนำ</button>
+    </div>
+    <p id='refStatus' class='muted' style='margin-bottom:0'></p>
+  </div>
+
+  <div class='card'>
+    <h3 style='margin-top:0'>ข้อมูลสมาชิก</h3>
+    <label class='muted' for='name'>ชื่อ</label>
+    <input id='name' class='input' autocomplete='name' placeholder='ชื่อ-นามสกุล'>
+
+    <label class='muted' for='email' style='margin-top:8px;display:block'>อีเมล (ไม่บังคับ)</label>
+    <input id='email' class='input' autocomplete='email' placeholder='name@example.com'>
+
+    <label class='muted' for='phone' style='margin-top:8px;display:block'>เบอร์โทร</label>
+    <input id='phone' class='input' autocomplete='tel' placeholder='08xxxxxxxx'>
+
+    <label class='muted' for='password' style='margin-top:8px;display:block'>รหัสผ่าน</label>
+    <input id='password' class='input' type='password' autocomplete='new-password' placeholder='อย่างน้อย 6 ตัวอักษร'>
+
+    <label class='muted' for='confirmPassword' style='margin-top:8px;display:block'>ยืนยันรหัสผ่าน</label>
+    <input id='confirmPassword' class='input' type='password' autocomplete='new-password' placeholder='กรอกรหัสผ่านซ้ำ'>
+
+    <div class='row' style='margin-top:12px'>
+      <button id='registerBtn' class='btn btn-primary' type='button'>สมัครสมาชิก</button>
+      <a href='account.html' class='btn btn-ghost' style='text-decoration:none'>กลับไปหน้า Account</a>
+    </div>
+  </div>
+</div>
+<div id='toast' class='toast'></div>
+<div id='bottomNav'></div>
+
+<script src='assets/api.js'></script><script src='assets/app.js'></script>
+<script>
+renderHeader('Register');
+renderBottomNav('account');
+
+const refCodeInput = document.getElementById('refCode');
+const refStatus = document.getElementById('refStatus');
+const validateRefBtn = document.getElementById('validateRefBtn');
+const registerBtn = document.getElementById('registerBtn');
+const nameInput = document.getElementById('name');
+const emailInput = document.getElementById('email');
+const phoneInput = document.getElementById('phone');
+const passwordInput = document.getElementById('password');
+const confirmPasswordInput = document.getElementById('confirmPassword');
+
+let refValidated = false;
+let refSource = null;
+
+function normalizeRefCode(value) {
+  return String(value || '').trim().toUpperCase();
+}
+
+function setRefStatusMessage(text, isError) {
+  refStatus.textContent = text;
+  refStatus.style.color = isError ? 'var(--danger)' : 'var(--ok)';
+}
+
+async function validateReferral(source) {
+  const code = normalizeRefCode(refCodeInput.value);
+  refCodeInput.value = code;
+
+  if (!code) {
+    refValidated = false;
+    refSource = null;
+    setRefStatusMessage('ยังไม่ได้ระบุรหัสผู้แนะนำ (ระบบจะใช้ผู้แนะนำเริ่มต้น)', false);
+    return true;
+  }
+
+  try {
+    const data = await api.get('/api/ref/validate', { code });
+    if (!data.exists) {
+      refValidated = false;
+      refSource = null;
+      setRefStatusMessage('ไม่พบรหัสผู้แนะนำนี้ กรุณาตรวจสอบหรือกรอกใหม่', true);
+      showToast('รหัสผู้แนะนำไม่ถูกต้อง', 'error');
+      return false;
+    }
+
+    refValidated = true;
+    refSource = source || 'manual';
+    setRefStatusMessage(`ผู้แนะนำ: ${data.name} (code ${data.member_code})`, false);
+    showToast('ตรวจสอบรหัสผู้แนะนำสำเร็จ', 'success');
+    return true;
+  } catch (e) {
+    refValidated = false;
+    refSource = null;
+    setRefStatusMessage('ไม่สามารถตรวจสอบรหัสผู้แนะนำได้ กรุณาลองใหม่อีกครั้ง', true);
+    showToast(e.message, 'error');
+    return false;
+  }
+}
+
+async function register() {
+  const name = String(nameInput.value || '').trim();
+  const email = String(emailInput.value || '').trim();
+  const phone = String(phoneInput.value || '').trim();
+  const password = String(passwordInput.value || '');
+  const confirmPassword = String(confirmPasswordInput.value || '');
+  const refCode = normalizeRefCode(refCodeInput.value);
+
+  if (!name || !phone || !password) {
+    showToast('กรุณากรอกชื่อ เบอร์โทร และรหัสผ่าน', 'error');
+    return;
+  }
+
+  if (password.length < 6) {
+    showToast('รหัสผ่านต้องอย่างน้อย 6 ตัวอักษร', 'error');
+    return;
+  }
+
+  if (password !== confirmPassword) {
+    showToast('รหัสผ่านและยืนยันรหัสผ่านไม่ตรงกัน', 'error');
+    return;
+  }
+
+  if (refCode && !refValidated) {
+    const ok = await validateReferral('manual');
+    if (!ok) return;
+  }
+
+  const payload = {
+    name,
+    phone,
+    password,
+    email: email || null,
+    referrer_member_code: refCode || null,
+    ref_source: refCode ? (refSource || 'manual') : null,
+  };
+
+  try {
+    const data = await api.post('/api/auth/register', payload);
+    if (data.token) {
+      shopSettings.setToken(data.token);
+    }
+    showToast('สมัครสมาชิกสำเร็จ', 'success');
+    setTimeout(() => {
+      location.href = 'index.html';
+    }, 500);
+  } catch (e) {
+    showToast(e.message, 'error');
+  }
+}
+
+validateRefBtn.addEventListener('click', () => validateReferral('manual'));
+registerBtn.addEventListener('click', register);
+
+refCodeInput.addEventListener('input', () => {
+  refValidated = false;
+  refSource = null;
+  refStatus.textContent = '';
+  refStatus.style.color = 'var(--muted)';
+});
+
+const refFromQuery = normalizeRefCode(qs('ref'));
+if (refFromQuery) {
+  refCodeInput.value = refFromQuery;
+  validateReferral('link');
+} else {
+  setRefStatusMessage('ยังไม่ได้ระบุรหัสผู้แนะนำ (ระบบจะใช้ผู้แนะนำเริ่มต้น)', false);
+}
+</script>
+</body></html>
EOF
)

# 4) commit + push
git add -A
git commit -m "feat: add register page with referral" || true
git push -u origin feat/wap-register-referral

echo "✅ Open PR: https://github.com/beehappy21/blifehealthy-core/compare/feat/wap-register-referral?expand=1"
