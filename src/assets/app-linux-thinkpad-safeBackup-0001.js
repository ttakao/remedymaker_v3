// assets/app.js
let currentUser = null;
let allData = { groups: [], subgroups: [], frequencies: [], custom_frequencies: [] };
let selectedGroupId = null;
let selectedSubgroupId = null;
let selectedFrequencyList = [];

let serialPort = null;
let waitSeconds = 20;
let isTransmitting = false;
let abortController = null;

document.addEventListener('DOMContentLoaded', async () => {
    const res = await callAPI('get_session_status');
    if (res.status === 'success') {
        loginSuccess(res.user);
    } else {
        showSection('login-section');
    }
});

async function callAPI(action, data = {}) {
    try {
        const response = await fetch(`api.php?action=${action}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        return await response.json();
    } catch (e) {
        return { status: 'error', message: '通信でエラーが発生しました。' };
    }
}

function showSection(id) {
    document.getElementById('login-section').classList.add('hidden');
    document.getElementById('main-section').classList.add('hidden');
    document.getElementById('admin-section').classList.add('hidden');
    document.getElementById(id).classList.remove('hidden');
}

// 認証処理
async function requestPasskey() {
    const email = document.getElementById('login-email').value;
    if (!email) return alert('メールアドレスを入力してください。');
    
    const res = await callAPI('login_request', { email });
    if (res.status === 'success') {
        alert(res.message);
        document.getElementById('login-step1').classList.add('hidden');
        document.getElementById('login-step2').classList.remove('hidden');
        if (res.debug_otp) {
            document.getElementById('debug-otp-area').innerText = `[デバッグ用コード]: ${res.debug_otp}`;
        }
    } else {
        alert(res.message);
    }
}

async function verifyPasskey() {
    const email = document.getElementById('login-email').value;
    const code = document.getElementById('login-code').value;
    if (!code) return alert('認証コードを入力してください。');

    const res = await callAPI('login_verify', { email, code });
    if (res.status === 'success') {
        loginSuccess(res.user);
    } else {
        alert(res.message);
    }
}

function loginSuccess(user) {
    currentUser = user;
    document.getElementById('login-user-name').innerText = user.name;
    document.getElementById('header-user-info').classList.remove('hidden');
    
    if (user.role === 'admin') {
        document.getElementById('admin-toggle-btn').classList.remove('hidden');
    } else {
        document.getElementById('admin-toggle-btn').classList.add('hidden');
    }
    
    showSection('main-section');
    loadApplicationData();
}

async function handleLogout() {
    const res = await callAPI('logout');
    alert(res.message);
    currentUser = null;
    document.getElementById('header-user-info').classList.add('hidden');
    document.getElementById('login-email').value = '';
    document.getElementById('login-code').value = '';
    document.getElementById('login-step1').classList.remove('hidden');
    document.getElementById('login-step2').classList.add('hidden');
    showSection('login-section');
}

// データ管理・レンダリング
async function loadApplicationData() {
    const res = await callAPI('get_data');
    if (res.status === 'success') {
        allData = res;
        renderGroups();
        renderCustomFrequencies();
    } else {
        alert(res.message);
    }
}

function renderGroups() {
    const tbody = document.querySelector('#groups-table tbody');
    tbody.innerHTML = '';
    allData.groups.forEach(g => {
        const tr = document.createElement('tr');
        tr.innerHTML = `<td>${escapeHtml(g.gname)}</td>`;
        tr.onclick = () => selectGroup(g.id, tr);
        tbody.appendChild(tr);
    });
}

function selectGroup(gid, trElement) {
    document.querySelectorAll('#groups-table tr').forEach(r => r.classList.remove('selected'));
    trElement.classList.add('selected');
    selectedGroupId = gid;
    selectedSubgroupId = null;
    document.querySelector('#frequencies-table tbody').innerHTML = '';
    
    const tbody = document.querySelector('#subgroups-table tbody');
    tbody.innerHTML = '';
    allData.subgroups.filter(s => s.gid == gid).forEach(s => {
        const tr = document.createElement('tr');
        tr.innerHTML = `<td>${escapeHtml(s.sname)}</td>`;
        tr.onclick = () => selectSubgroup(s.id, tr);
        tbody.appendChild(tr);
    });
}

function selectSubgroup(sid, trElement) {
    document.querySelectorAll('#subgroups-table tr').forEach(r => r.classList.remove('selected'));
    trElement.classList.add('selected');
    selectedSubgroupId = sid;

    const tbody = document.querySelector('#frequencies-table tbody');
    tbody.innerHTML = '';
     allData.frequencies.filter(f => f.sid == sid).forEach(f => {
        const tr = document.createElement('tr');
        // ★ HTMLを2列に変更
        tr.innerHTML = `
            <td>
                <div style="font-weight: 500;">${escapeHtml(f.jname)}</div>
                ${f.ename ? `<div style="font-size: 11px; color: #7f8c8d; margin-top: 2px;">${escapeHtml(f.ename)}</div>` : ''}
            </td>
            <td>${escapeHtml(f.flist)}</td>
        `;
        tr.onclick = () => selectFrequencyItem(f.flist, tr);
        tbody.appendChild(tr);
    });
}

function selectFrequencyItem(flist, trElement) {
    document.querySelectorAll('#frequencies-table tr').forEach(r => r.classList.remove('selected'));
    document.querySelectorAll('#custom-table tr').forEach(r => r.classList.remove('selected'));
    trElement.classList.add('selected');

    selectedFrequencyList = flist.split(',').map(n => n.trim());
    document.getElementById('target-frequencies').value = selectedFrequencyList.join(', ');
}

function filterFrequencies() {
    const q = document.getElementById('search-input').value.toLowerCase();
    const tbody = document.querySelector('#frequencies-table tbody');
    tbody.innerHTML = '';
    
    if (!q) {
        if (selectedSubgroupId) selectSubgroup(selectedSubgroupId, document.querySelector('#subgroups-table tr.selected'));
        return;
    }

     allData.frequencies.filter(f => 
        f.jname.toLowerCase().includes(q) || 
        (f.ename && f.ename.toLowerCase().includes(q)) || 
        f.flist.includes(q)
    ).forEach(f => {
        const tr = document.createElement('tr');
        // ★ 同様に2列に変更します
        tr.innerHTML = `
            <td>
                <div style="font-weight: 500;">${escapeHtml(f.jname)}</div>
                ${f.ename ? `<div style="font-size: 11px; color: #7f8c8d; margin-top: 2px;">${escapeHtml(f.ename)}</div>` : ''}
            </td>
            <td>${escapeHtml(f.flist)}</td>
        `;
        tr.onclick = () => selectFrequencyItem(f.flist, tr);
        tbody.appendChild(tr);
    });
}

// シリアル通信制御 (Web Serial API)
function showConnectModal() {
    document.getElementById('interval-sec').value = waitSeconds;
    document.getElementById('selected-port-name').innerText = serialPort ? "選択済み" : "未選択";
    showModal('connect-modal');
}

async function requestSerialPort() {
    try {
        if (!navigator.serial) {
            alert("Web Serial API非対応のブラウザです。Chromeでお試しください。");
            return;
        }
        serialPort = await navigator.serial.requestPort();
        document.getElementById('selected-port-name').innerText = "デバイス取得完了";
        document.getElementById('serial-status').innerText = "取得済 (未オープン)";
        document.getElementById('serial-status').style.color = "orange";
    } catch (e) {
        alert("接続ポート選択エラー: " + e.message);
    }
}

function saveConnectionSettings() {
    waitSeconds = parseInt(document.getElementById('interval-sec').value) || 20;
    closeModal('connect-modal');
}

const sleep = ms => new Promise(resolve => setTimeout(resolve, ms));

async function startTransmission() {
    if (!serialPort) return alert("デバイス（生成機）を選択してください。");
    if (selectedFrequencyList.length === 0) return alert("周波数が選択されていません。");

    isTransmitting = true;
    document.getElementById('start-btn').disabled = true;
    document.getElementById('stop-btn').disabled = false;
    document.getElementById('progress-area').classList.remove('hidden');
    document.getElementById('serial-status').innerText = "通信稼働中";
    document.getElementById('serial-status').style.color = "green";

    let writer = null;
    let reader = null;

    try {
        await serialPort.open({ baudRate: 115200 });
        await sleep(2000); // 起動ラグ待機

        writer = serialPort.writable.getWriter();
        const encoder = new TextEncoder();

        // 疎通確認
        await writer.write(encoder.encode("who;"));
        reader = serialPort.readable.getReader();
        const decoder = new TextDecoder();
        let received = '';
        const startCheck = Date.now();
        
        while (Date.now() - startCheck < 1500) {
            const { value } = await Promise.race([
                reader.read(),
                new Promise(resolve => setTimeout(() => resolve({ value: null }), 200))
            ]);
            if (value) {
                received += decoder.decode(value);
                if (received.includes("AD9833")) break;
            }
        }
        reader.releaseLock();
        reader = null;

        if (!received.includes("AD9833")) {
            throw new Error("デバイス疎通（who）確認に失敗しました。");
        }

        const total = selectedFrequencyList.length;
        for (let i = 0; i < total; i++) {
            if (!isTransmitting) break;

            const freq = selectedFrequencyList[i];
            document.getElementById('current-freq').innerText = freq;
            document.getElementById('overall-progress').innerText = `${i + 1} / ${total}`;
            document.getElementById('progress-bar-inner').style.width = (((i + 1) / total) * 100) + '%';

            await writer.write(encoder.encode(`${freq};`));

            for (let s = waitSeconds; s > 0; s--) {
                if (!isTransmitting) break;
                document.getElementById('countdown-sec').innerText = s;
                await sleep(1000);
            }
        }
    } catch (e) {
        alert("シリアル通信エラー: " + e.message);
    } finally {
        isTransmitting = false;
        if (writer) try { writer.releaseLock(); } catch(err){}
        if (reader) try { reader.releaseLock(); } catch(err){}
        try { await serialPort.close(); } catch(err){}

        document.getElementById('start-btn').disabled = false;
        document.getElementById('stop-btn').disabled = true;
        document.getElementById('progress-area').classList.add('hidden');
        document.getElementById('serial-status').innerText = "接続待機中 (切断)";
        document.getElementById('serial-status').style.color = "orange";
        alert(abortController ? "処理を停止しました。" : "すべての処理が終了しました。");
        abortController = null;
    }
}

async function stopTransmission() {
    if (!isTransmitting) return;
    isTransmitting = false;
    abortController = true;

    try {
        if (serialPort && serialPort.writable) {
            const writer = serialPort.writable.getWriter();
            const encoder = new TextEncoder();
            await writer.write(encoder.encode("0;")); // 安全に停止
            writer.releaseLock();
        }
    } catch(e) {}
}

// カスタム周波数 CRUD
function renderCustomFrequencies() {
    const tbody = document.querySelector('#custom-table tbody');
    tbody.innerHTML = '';
    allData.custom_frequencies.forEach(c => {
        const tr = document.createElement('tr');
        tr.innerHTML = `<td>${escapeHtml(c.name)}</td><td>${escapeHtml(c.flist)}</td>`;
        tr.onclick = () => selectFrequencyItem(c.flist, tr);
        tr.ondblclick = () => showCustomModal(c);
        tbody.appendChild(tr);
    });
}

function showCustomModal(item = null) {
    if (item) {
        document.getElementById('custom-modal-title').innerText = "カスタムデータの編集";
        document.getElementById('custom-id').value = item.id;
        document.getElementById('custom-name').value = item.name;
        document.getElementById('custom-flist').value = item.flist;
        document.getElementById('custom-delete-btn').classList.remove('hidden');
    } else {
        document.getElementById('custom-modal-title').innerText = "新規カスタムデータの追加";
        document.getElementById('custom-id').value = '';
        document.getElementById('custom-name').value = '';
        document.getElementById('custom-flist').value = '';
        document.getElementById('custom-delete-btn').classList.add('hidden');
    }
    showModal('custom-modal');
}

async function saveCustomFrequency() {
    const id = document.getElementById('custom-id').value;
    const name = document.getElementById('custom-name').value;
    const flist = document.getElementById('custom-flist').value;

    if (!name || !flist) return alert('入力不足です。');
    if (!/^[0-9.,]+$/.test(flist)) return alert('半角数字、ピリオド、カンマのみ使用可能です。');

    const res = await callAPI('save_custom_frequency', { id, name, flist });
    alert(res.message);
    if (res.status === 'success') {
        closeModal('custom-modal');
        loadApplicationData();
    }
}

async function deleteCustomFrequency() {
    if (!confirm('削除してよろしいですか？')) return;
    const id = document.getElementById('custom-id').value;
    const res = await callAPI('delete_custom_frequency', { id });
    alert(res.message);
    if (res.status === 'success') {
        closeModal('custom-modal');
        loadApplicationData();
    }
}

// 管理モード
let currentAdminView = 'main';
function toggleAdminView() {
    if (currentAdminView === 'main') {
        currentAdminView = 'admin';
        showSection('admin-section');
        switchAdminTab('users');
    } else {
        currentAdminView = 'main';
        showSection('main-section');
        loadApplicationData();
    }
}

function switchAdminTab(tabName) {
    document.querySelectorAll('.admin-tab').forEach(t => t.classList.add('hidden'));
    document.getElementById(`admin-tab-${tabName}`).classList.remove('hidden');
    
    if (tabName === 'users') adminLoadUsers();
    else if (tabName === 'masters') adminLoadMasters();
}

async function adminLoadUsers() {
    const res = await callAPI('admin_get_users');
    if (res.status !== 'success') return alert(res.message);

    const tbody = document.querySelector('#admin-users-table tbody');
    tbody.innerHTML = '';
    res.users.forEach(u => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${escapeHtml(u.name)}</td>
            <td>${escapeHtml(u.email)}</td>
            <td>${u.paid == 1 ? '支払済' : '未払'}</td>
            <td>${u.role === 'admin' ? '管理者' : '一般'}</td>
            <td>${escapeHtml(u.learnings.join(', '))}</td>
            <td>
                <button onclick="showUserFormModal(${JSON.stringify(u).replace(/"/g, '&quot;')})">編集</button>
                ${u.locked_until ? `<button onclick="adminUnlockUser(${u.id})" style="background-color:#f39c12;">解除</button>` : ''}
            </td>
        `;
        tbody.appendChild(tr);
    });
}

function showUserFormModal(user = null) {
    if (user) {
        document.getElementById('admin-user-id').value = user.id;
        document.getElementById('admin-user-name').value = user.name;
        document.getElementById('admin-user-email').value = user.email;
        document.getElementById('admin-user-paid').value = user.paid;
        document.getElementById('admin-user-role').value = user.role;
        document.getElementById('admin-user-learnings').value = user.learnings.join(', ');
        document.getElementById('admin-user-memo').value = user.memo || '';
        document.getElementById('admin-user-delete-btn').classList.remove('hidden');
    } else {
        document.getElementById('admin-user-id').value = '';
        document.getElementById('admin-user-name').value = '';
        document.getElementById('admin-user-email').value = '';
        document.getElementById('admin-user-paid').value = '0';
        document.getElementById('admin-user-role').value = 'regular';
        document.getElementById('admin-user-learnings').value = '';
        document.getElementById('admin-user-memo').value = '';
        document.getElementById('admin-user-delete-btn').classList.add('hidden');
    }
    showModal('user-form-modal');
}

async function adminSaveUser() {
    const id = document.getElementById('admin-user-id').value;
    const name = document.getElementById('admin-user-name').value;
    const email = document.getElementById('admin-user-email').value;
    const paid = document.getElementById('admin-user-paid').value;
    const role = document.getElementById('admin-user-role').value;
    const learningsStr = document.getElementById('admin-user-learnings').value;
    const memo = document.getElementById('admin-user-memo').value;

    const learnings = learningsStr.split(',').map(s => s.trim()).filter(s => s !== '');
    const res = await callAPI('admin_save_user', { id, name, email, paid, role, learnings, memo });
    alert(res.message);
    if (res.status === 'success') {
        closeModal('user-form-modal');
        adminLoadUsers();
    }
}

async function adminDeleteUser() {
    if (!confirm('このユーザーを完全に削除しますか？')) return;
    const id = document.getElementById('admin-user-id').value;
    const res = await callAPI('admin_delete_user', { id });
    alert(res.message);
    if (res.status === 'success') {
        closeModal('user-form-modal');
        adminLoadUsers();
    }
}

async function adminUnlockUser(id) {
    const res = await callAPI('admin_unlock_user', { id });
    alert(res.message);
    if (res.status === 'success') adminLoadUsers();
}

// マスタデータメンテナンス
let adminMasters = { groups: [], subgroups: [], frequencies: [] };

async function adminLoadMasters() {
    const res = await callAPI('admin_get_masters');
    if (res.status !== 'success') return alert(res.message);

    adminMasters = res;

    // グループ描画
    const gtbody = document.querySelector('#admin-groups-table tbody');
    gtbody.innerHTML = '';
    res.groups.forEach(g => {
        const tr = document.createElement('tr');
        tr.innerHTML = `<td>${g.id}</td><td>${escapeHtml(g.gname)}</td><td>${escapeHtml(g.learning_id || '')}</td>
        <td>
            <button onclick="showGroupModal(${JSON.stringify(g).replace(/"/g, '&quot;')})">編</button>
            <button onclick="adminDeleteMaster('group', ${g.id})" class="danger">消</button>
        </td>`;
        gtbody.appendChild(tr);
    });

    // サブグループ描画
    const stbody = document.querySelector('#admin-subgroups-table tbody');
    stbody.innerHTML = '';
    res.subgroups.forEach(s => {
        const tr = document.createElement('tr');
        tr.innerHTML = `<td>${s.id}</td><td>${s.gid}</td><td>${escapeHtml(s.sname)}</td>
        <td>
            <button onclick="showSubgroupModal(${JSON.stringify(s).replace(/"/g, '&quot;')})">編</button>
            <button onclick="adminDeleteMaster('subgroup', ${s.id})" class="danger">消</button>
        </td>`;
        stbody.appendChild(tr);
    });

    // 周波数リスト描画
    const ftbody = document.querySelector('#admin-frequencies-table tbody');
    ftbody.innerHTML = '';
    res.frequencies.forEach(f => {
        const tr = document.createElement('tr');
        tr.innerHTML = `<td>${f.id}</td><td>${f.sid}</td><td>${escapeHtml(f.jname)}</td><td>${escapeHtml(f.ename || '')}</td><td>${escapeHtml(f.flist)}</td>
        <td>
            <button onclick="showFrequencyModal(${JSON.stringify(f).replace(/"/g, '&quot;')})">編集</button>
            <button onclick="adminDeleteMaster('frequency', ${f.id})" class="danger">削除</button>
        </td>`;
        ftbody.appendChild(tr);
    });
}

function showGroupModal(item = null) {
    if (item) {
        document.getElementById('master-group-id').value = item.id;
        document.getElementById('master-group-name').value = item.gname;
        document.getElementById('master-group-learning').value = item.learning_id || '';
    } else {
        document.getElementById('master-group-id').value = '';
        document.getElementById('master-group-name').value = '';
        document.getElementById('master-group-learning').value = '';
    }
    showModal('group-modal');
}

async function saveMasterGroup() {
    const id = document.getElementById('master-group-id').value;
    const gname = document.getElementById('master-group-name').value;
    const learning_id = document.getElementById('master-group-learning').value;
    const res = await callAPI('admin_save_group', { id, gname, learning_id });
    alert(res.message);
    if (res.status === 'success') { closeModal('group-modal'); adminLoadMasters(); }
}

function showSubgroupModal(item = null) {
    const select = document.getElementById('master-subgroup-gid');
    select.innerHTML = '';
    adminMasters.groups.forEach(g => {
        const opt = document.createElement('option');
        opt.value = g.id; opt.innerText = g.gname;
        select.appendChild(opt);
    });

    if (item) {
        document.getElementById('master-subgroup-id').value = item.id;
        document.getElementById('master-subgroup-gid').value = item.gid;
        document.getElementById('master-subgroup-name').value = item.sname;
    } else {
        document.getElementById('master-subgroup-id').value = '';
        document.getElementById('master-subgroup-name').value = '';
    }
    showModal('subgroup-modal');
}

async function saveMasterSubgroup() {
    const id = document.getElementById('master-subgroup-id').value;
    const gid = document.getElementById('master-subgroup-gid').value;
    const sname = document.getElementById('master-subgroup-name').value;
    const res = await callAPI('admin_save_subgroup', { id, gid, sname });
    alert(res.message);
    if (res.status === 'success') { closeModal('subgroup-modal'); adminLoadMasters(); }
}

function showFrequencyModal(item = null) {
    const select = document.getElementById('master-freq-sid');
    select.innerHTML = '';
    adminMasters.subgroups.forEach(s => {
        const opt = document.createElement('option');
        opt.value = s.id; opt.innerText = s.sname;
        select.appendChild(opt);
    });

    if (item) {
        document.getElementById('master-freq-id').value = item.id;
        document.getElementById('master-freq-sid').value = item.sid;
        document.getElementById('master-freq-jname').value = item.jname;
        document.getElementById('master-freq-ename').value = item.ename || '';
        document.getElementById('master-freq-flist').value = item.flist;
    } else {
        document.getElementById('master-freq-id').value = '';
        document.getElementById('master-freq-jname').value = '';
        document.getElementById('master-freq-ename').value = '';
        document.getElementById('master-freq-flist').value = '';
    }
    showModal('frequency-modal');
}

async function saveMasterFrequency() {
    const id = document.getElementById('master-freq-id').value;
    const sid = document.getElementById('master-freq-sid').value;
    const jname = document.getElementById('master-freq-jname').value;
    const ename = document.getElementById('master-freq-ename').value;
    const flist = document.getElementById('master-freq-flist').value;

    const res = await callAPI('admin_save_frequency', { id, sid, jname, ename, flist });
    alert(res.message);
    if (res.status === 'success') { closeModal('frequency-modal'); adminLoadMasters(); }
}

async function adminDeleteMaster(type, id) {
    if (!confirm('このマスタデータを削除しますか？子データも自動的に全削除されます。')) return;
    const res = await callAPI('admin_delete_master', { type, id });
    alert(res.message);
    if (res.status === 'success') adminLoadMasters();
}

// モーダル等共通ヘルパー
function showModal(id) { document.getElementById(id).style.display = 'flex'; }
function closeModal(id) { document.getElementById(id).style.display = 'none'; }
function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}