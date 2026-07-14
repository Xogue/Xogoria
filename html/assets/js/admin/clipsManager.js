(() => {
    'use strict';
    const root = document.getElementById('clipAdminRoot');
    if (!root) return;

    const csrfToken = document.querySelector('meta[name="admin-csrf-token"]')?.content || '';
    let clips = [];
    let deletions = [];
    let selectedReview = null;
    let selectedLibrary = null;

    const byId = id => document.getElementById(id);
    const alertBox = byId('clipAdminAlert');
    const escape = value => String(value ?? '').replace(/[&<>'"]/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[char]));
    const showError = message => { alertBox.textContent = message; alertBox.hidden = false; };
    const clearError = () => { alertBox.hidden = true; };

    const request = async (payload = null) => {
        const response = await fetch('/api/clips/clipsAdmin.php', payload ? {
            method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({...payload, csrfToken})
        } : {credentials: 'same-origin'});
        const body = await response.json().catch(() => ({success:false, message:'Invalid server response.'}));
        if (!response.ok || body.success === false) throw new Error(body.message || 'Clip request failed.');
        return body;
    };

    const embed = (container, clip) => {
        container.replaceChildren();
        if (!clip) { container.innerHTML = '<div class="clipAdminFallback">Select a clip.</div>'; return; }
        const iframe = document.createElement('iframe');
        iframe.allowFullscreen = true;
        iframe.src = `https://clips.twitch.tv/embed?clip=${encodeURIComponent(clip.id)}&parent=${encodeURIComponent(location.hostname)}`;
        iframe.title = clip.title || `Twitch clip ${clip.id}`;
        container.append(iframe);
    };

    const clipItem = (clip, selected, callback) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'clipAdminItem' + (selected?.id === clip.id ? ' selected' : '');
        button.innerHTML = `<strong>${escape(clip.customTitle || clip.title || clip.id)}</strong><span>${escape(clip.creatorName || 'Unknown creator')} · ${Number(clip.viewCount || 0).toLocaleString()} views</span>`;
        button.addEventListener('click', () => callback(clip));
        return button;
    };

    const selectReview = clip => {
        selectedReview = clip;
        embed(byId('clipReviewPlayer'), clip);
        byId('clipReviewTitle').textContent = clip.title || clip.id;
        byId('clipReviewDetails').innerHTML = `<a href="${escape(clip.url || `https://clips.twitch.tv/${clip.id}`)}" target="_blank" rel="noopener">Open on Twitch</a> · ${escape(clip.creatorName || 'Unknown creator')}`;
        ['clipApproveBtn','clipIgnoreBtn','clipDeleteRequestBtn'].forEach(id => { byId(id).disabled = false; });
        renderReview();
    };

    const selectLibrary = clip => {
        selectedLibrary = clip;
        embed(byId('clipLibraryPlayer'), clip);
        byId('clipCustomTitle').value = clip.customTitle || '';
        byId('clipFavorite').checked = Boolean(clip.favorite);
        byId('clipEnabled').checked = Boolean(clip.enabled);
        byId('clipStartOffset').value = Number(clip.startOffset || 0);
        byId('clipMaxDuration').value = Number(clip.maxDuration || 0);
        byId('clipPlayCount').value = Number(clip.playCount || 0);
        byId('clipSaveBtn').disabled = false;
        byId('clipLibraryDeleteBtn').disabled = false;
        renderLibrary();
    };

    const renderReview = () => {
        const status = byId('clipReviewFilter').value;
        const wanted = {pending:0, ignored:2, deletion:3};
        const rows = clips.filter(clip => status === 'all' || Number(clip.reviewStatus || 0) === wanted[status]);
        const list = byId('clipReviewList'); list.replaceChildren();
        rows.forEach(clip => list.append(clipItem(clip, selectedReview, selectReview)));
        if (!rows.length) list.innerHTML = '<div class="clipAdminFallback">No clips in this view.</div>';
        const pending = clips.filter(clip => Number(clip.reviewStatus || 0) === 0).length;
        const badge = byId('clipQueueBadge'); badge.textContent = String(pending); badge.hidden = pending === 0;
    };

    const renderLibrary = () => {
        const list = byId('clipLibraryList'); list.replaceChildren();
        const rows = clips.filter(clip => Number(clip.reviewStatus || 0) === 1);
        rows.forEach(clip => list.append(clipItem(clip, selectedLibrary, selectLibrary)));
        if (!rows.length) list.innerHTML = '<div class="clipAdminFallback">No approved clips.</div>';
    };

    const renderDeletions = () => {
        const list = byId('clipDeletionList'); list.replaceChildren();
        deletions.forEach(record => {
            const row = document.createElement('article'); row.className = 'clipDeletionItem';
            row.innerHTML = `<strong>${escape(record.clipId)}</strong><span>Requested ${escape(record.requestedAt)} by ${escape(record.requestedBy)}</span><div><a href="${escape(record.twitchUrl)}" target="_blank" rel="noopener">Twitch clip</a>${record.backblazeUrl ? ` · <a href="${escape(record.backblazeUrl)}" target="_blank" rel="noopener">Backblaze file</a>` : ''}</div>`;
            list.append(row);
        });
        if (!deletions.length) list.innerHTML = '<div class="clipAdminFallback">No deletion requests.</div>';
    };

    const reload = async () => {
        clearError();
        try {
            const body = await request(); clips = body.clips || []; deletions = body.deletions || [];
            if (body.warning) showError(`Twitch could not be refreshed: ${body.warning}. Stored clips are still available.`);
            renderReview(); renderLibrary(); renderDeletions();
        } catch (error) { showError(error.message); }
    };

    root.querySelectorAll('[data-clip-view]').forEach(button => button.addEventListener('click', () => {
        root.querySelectorAll('[data-clip-view]').forEach(item => item.classList.toggle('active', item === button));
        root.querySelectorAll('[data-clip-panel]').forEach(panel => { panel.hidden = panel.dataset.clipPanel !== button.dataset.clipView; });
    }));
    byId('clipReviewFilter').addEventListener('change', renderReview);

    byId('clipApproveBtn').addEventListener('click', async () => { if (!selectedReview) return; try { await request({action:'approve', clipId:selectedReview.id, clip:selectedReview}); await reload(); } catch(error) { showError(error.message); } });
    byId('clipIgnoreBtn').addEventListener('click', async () => { if (!selectedReview) return; try { await request({action:'ignore', clipId:selectedReview.id}); await reload(); } catch(error) { showError(error.message); } });
    const requestDeletion = async clip => {
        if (!clip || !confirm('Record this clip for deletion and remove it from the collection? External deletion may still require the retained links.')) return;
        try { await request({action:'requestDeletion', clipId:clip.id}); selectedReview = selectedLibrary = null; await reload(); } catch(error) { showError(error.message); }
    };
    byId('clipDeleteRequestBtn').addEventListener('click', () => requestDeletion(selectedReview));
    byId('clipLibraryDeleteBtn').addEventListener('click', () => requestDeletion(selectedLibrary));
    byId('clipSaveBtn').addEventListener('click', async () => {
        if (!selectedLibrary) return;
        const data = {customTitle:byId('clipCustomTitle').value, favorite:byId('clipFavorite').checked, enabled:byId('clipEnabled').checked, startOffset:byId('clipStartOffset').value, maxDuration:byId('clipMaxDuration').value, playCount:byId('clipPlayCount').value};
        try { await request({action:'save', clipId:selectedLibrary.id, data}); await reload(); } catch(error) { showError(error.message); }
    });

    reload();
})();
