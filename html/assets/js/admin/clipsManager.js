(() => {
    'use strict';

    const root = document.getElementById('clipAdminRoot');
    if (!root) return;

    const csrfToken = document.querySelector('meta[name="admin-csrf-token"]')?.content || '';
    const byId = id => document.getElementById(id);
    const alertBox = byId('clipAdminAlert');
    const noticeBox = byId('clipAdminNotice');
    const player = byId('clipPlayer');
    const groupedList = byId('clipGroupedList');
    let clips = [];
    let deletions = [];
    let selectedClip = null;
    let noticeTimer = null;
    let clipActionBusy = false;
    const collapsedGroups = new Set();

    const statusDefinitions = [
        {status: 0, key: 'needsReview', label: 'Needs review', empty: 'No clips are waiting for review.'},
        {status: 2, key: 'ignored', label: 'Ignored', empty: 'No clips have been ignored.'},
        {status: 1, key: 'approved', label: 'Approved', empty: 'No clips have been approved.'},
        {status: 3, key: 'deletion', label: 'Deletion requested', empty: 'No clips have a deletion request.'}
    ];

    const escape = value => String(value ?? '').replace(/[&<>'"]/g, character => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;'
    }[character]));

    const statusDefinition = status => statusDefinitions.find(item => item.status === Number(status)) || statusDefinitions[0];
    const showError = message => {
        alertBox.textContent = message;
        alertBox.hidden = false;
    };
    const clearError = () => { alertBox.hidden = true; };
    const showNotice = message => {
        window.clearTimeout(noticeTimer);
        noticeBox.textContent = message;
        noticeBox.hidden = false;
        noticeTimer = window.setTimeout(() => { noticeBox.hidden = true; }, 3200);
    };

    const request = async (payload = null) => {
        const response = await fetch('/api/clips/clipsAdmin.php', payload ? {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({...payload, csrfToken})
        } : {credentials: 'same-origin'});
        const body = await response.json().catch(() => ({success: false, message: 'Invalid server response.'}));
        if (!response.ok || body.success === false) throw new Error(body.message || 'Clip request failed.');
        return body;
    };

    const deletionFor = clipId => deletions.find(record => record.clipId === clipId) || null;

    const embed = clip => {
        player.replaceChildren();
        if (!clip) {
            player.innerHTML = '<div class="clipAdminFallback">Select a clip to begin.</div>';
            return;
        }

        if (clip.localUrl) {
            const video = document.createElement('video');
            video.src = clip.localUrl;
            video.controls = true;
            video.preload = 'metadata';
            const startAt = Math.max(0, Number(clip.startOffset || 0));
            const endAt = Math.max(0, Number(clip.maxDuration || 0));

            video.addEventListener('loadedmetadata', () => {
                if (startAt > 0 && startAt < video.duration) video.currentTime = startAt;
            }, {once: true});
            if (endAt > startAt) {
                video.addEventListener('timeupdate', () => {
                    if (video.currentTime >= endAt) video.pause();
                });
                video.addEventListener('play', () => {
                    if (video.currentTime >= endAt) video.currentTime = startAt;
                });
            }
            player.append(video);
            return;
        }

        const iframe = document.createElement('iframe');
        iframe.allowFullscreen = true;
        iframe.allow = 'autoplay; fullscreen';
        iframe.src = `https://clips.twitch.tv/embed?clip=${encodeURIComponent(clip.id)}&parent=${encodeURIComponent(location.hostname)}`;
        iframe.title = clip.title || `Twitch clip ${clip.id}`;
        player.append(iframe);
    };

    const sortNewestFirst = (first, second) => {
        const firstDate = Date.parse(first.createdAt || '') || 0;
        const secondDate = Date.parse(second.createdAt || '') || 0;
        if (firstDate !== secondDate) return secondDate - firstDate;
        return String(first.title || first.id).localeCompare(String(second.title || second.id));
    };

    const clipCard = (clip, definition) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = `clipAdminItem clipAdminItem--${definition.key}`;
        button.dataset.clipId = clip.id;
        if (selectedClip?.id === clip.id) button.classList.add('selected');

        const thumbnail = clip.thumbnailUrl
            ? `<span class="clipAdminThumb"><img src="${escape(clip.thumbnailUrl)}" alt=""></span>`
            : '<span class="clipAdminThumb clipAdminThumbFallback" aria-hidden="true">Clip</span>';
        const badges = [];
        if (clip.favorite) badges.push('Prioritized');
        if (definition.status === 1 && !clip.enabled) badges.push('Unpublished');
        if (clip.audioNormalized) badges.push('Audio normalized');
        const created = clip.createdAt ? String(clip.createdAt).slice(0, 10) : '';

        button.innerHTML = `${thumbnail}<span class="clipAdminItemContent"><strong class="clipAdminItemTitle">${escape(clip.customTitle || clip.title || clip.id)}</strong><span class="clipAdminItemMeta"><span>${escape(clip.creatorName || 'Unknown creator')} · ${Number(clip.viewCount || 0).toLocaleString()} views</span><span>${escape(created)}</span></span>${badges.length ? `<span class="clipAdminItemBadges">${escape(badges.join(' · '))}</span>` : ''}</span>`;
        button.addEventListener('click', () => selectClip(clip));
        return button;
    };

    const followSelectedCard = () => {
        if (!selectedClip) return;
        window.requestAnimationFrame(() => {
            const card = Array.from(groupedList.querySelectorAll('[data-clip-id]'))
                .find(item => item.dataset.clipId === selectedClip.id);
            card?.scrollIntoView({behavior: 'smooth', block: 'nearest'});
        });
    };

    const renderGroups = (followSelection = false) => {
        groupedList.replaceChildren();

        statusDefinitions.forEach(definition => {
            const rows = clips
                .filter(clip => Number(clip.reviewStatus || 0) === definition.status)
                .sort(sortNewestFirst);
            const section = document.createElement('section');
            section.className = `clipStatusGroup clipStatusGroup--${definition.key}`;
            const collapsed = collapsedGroups.has(definition.key);
            if (collapsed) section.classList.add('clipStatusGroup--collapsed');
            const bodyId = `clipStatusGroupBody-${definition.key}`;
            section.innerHTML = `<button type="button" class="clipStatusGroupHeader" data-clip-status-toggle="${definition.key}" aria-expanded="${collapsed ? 'false' : 'true'}" aria-controls="${bodyId}"><span><span class="clipStatusGroupChevron" aria-hidden="true">&#9662;</span>${definition.label}</span><span>${rows.length}</span></button>`;
            const body = document.createElement('div');
            body.className = 'clipStatusGroupBody';
            body.id = bodyId;
            body.hidden = collapsed;
            if (rows.length) {
                rows.forEach(clip => body.append(clipCard(clip, definition)));
            } else {
                body.innerHTML = `<div class="clipStatusGroupEmpty">${definition.empty}</div>`;
            }
            section.append(body);
            groupedList.append(section);
        });

        const pending = clips.filter(clip => Number(clip.reviewStatus || 0) === 0).length;
        const badge = byId('clipQueueBadge');
        badge.textContent = `${pending} awaiting review`;
        badge.hidden = pending === 0;
        if (followSelection) followSelectedCard();
    };

    groupedList.addEventListener('click', event => {
        const toggle = event.target.closest('[data-clip-status-toggle]');
        if (!toggle || !groupedList.contains(toggle)) return;
        const key = toggle.dataset.clipStatusToggle;
        const section = toggle.closest('.clipStatusGroup');
        const body = section?.querySelector('.clipStatusGroupBody');
        if (!key || !section || !body) return;

        const collapse = toggle.getAttribute('aria-expanded') === 'true';
        toggle.setAttribute('aria-expanded', collapse ? 'false' : 'true');
        body.hidden = collapse;
        section.classList.toggle('clipStatusGroup--collapsed', collapse);
        if (collapse) collapsedGroups.add(key);
        else collapsedGroups.delete(key);
    });

    const setActionVisibility = (buttonId, visible) => {
        const button = byId(buttonId);
        button.hidden = !visible;
        if (visible && !clipActionBusy) button.disabled = false;
    };

    const setClipActionBusy = busy => {
        clipActionBusy = busy;
        const controls = byId('clipSelectionControls');
        controls.classList.toggle('clipSelectionControls--busy', busy);
        if (busy) controls.setAttribute('aria-busy', 'true');
        else controls.removeAttribute('aria-busy');
        if (busy) controls.querySelectorAll('button').forEach(button => { button.disabled = true; });
    };

    const renderDeletionDetails = clip => {
        const container = byId('clipDeletionDetails');
        if (Number(clip.reviewStatus || 0) !== 3) {
            container.hidden = true;
            container.replaceChildren();
            return;
        }

        const record = deletionFor(clip.id);
        const twitchUrl = record?.twitchUrl || clip.url || `https://clips.twitch.tv/${encodeURIComponent(clip.id)}`;
        container.innerHTML = `<strong>Manual deletion required</strong><p>This clip is unpublished. Use the retained provider links to finish deleting it, or return it to review.</p><div><a href="${escape(twitchUrl)}" target="_blank" rel="noopener">Open Twitch clip</a>${record?.backblazeUrl ? ` <span>·</span> <a href="${escape(record.backblazeUrl)}" target="_blank" rel="noopener">Open stored file</a>` : ''}</div>`;
        container.hidden = false;
    };

    const renderSelection = (refreshPlayer = true) => {
        if (!selectedClip) return;
        const status = Number(selectedClip.reviewStatus || 0);
        const definition = statusDefinition(status);
        if (refreshPlayer) embed(selectedClip);

        byId('clipSelectionControls').hidden = false;
        byId('clipSelectedTitle').textContent = selectedClip.customTitle || selectedClip.title || selectedClip.id;
        byId('clipSelectedDetails').innerHTML = `<a href="${escape(selectedClip.url || `https://clips.twitch.tv/${selectedClip.id}`)}" target="_blank" rel="noopener">Open on Twitch</a><span>${escape(selectedClip.creatorName || 'Unknown creator')} · ${Number(selectedClip.viewCount || 0).toLocaleString()} views</span>`;
        const statusBadge = byId('clipSelectedStatus');
        statusBadge.className = `clipSelectedStatus clipSelectedStatus--${definition.key}`;
        statusBadge.textContent = definition.label;
        statusBadge.hidden = false;

        const approved = status === 1;
        byId('clipApprovedSettings').hidden = !approved;
        if (approved) {
            byId('clipCustomTitle').value = selectedClip.customTitle || '';
            byId('clipFavorite').checked = Boolean(selectedClip.favorite);
            byId('clipEnabled').checked = Boolean(selectedClip.enabled);
            byId('clipStartOffset').value = Number(selectedClip.startOffset || 0);
            byId('clipMaxDuration').value = Number(selectedClip.maxDuration || 0);
            byId('clipPlayCount').value = Number(selectedClip.playCount || 0);

            const normalizeButton = byId('clipNormalizeBtn');
            byId('clipSaveBtn').disabled = false;
            const audioStatus = byId('clipAudioStatus');
            const hasStoredFile = Boolean(selectedClip.localUrl);
            normalizeButton.hidden = Boolean(selectedClip.audioNormalized);
            normalizeButton.disabled = !hasStoredFile;
            if (selectedClip.audioNormalized) {
                audioStatus.className = 'clipAudioStatus clipAudioStatus--complete';
                audioStatus.textContent = 'Audio normalized to −16 LUFS.';
            } else if (hasStoredFile) {
                audioStatus.className = 'clipAudioStatus';
                audioStatus.textContent = 'Stored audio has not been normalized yet.';
            } else {
                audioStatus.className = 'clipAudioStatus clipAudioStatus--unavailable';
                audioStatus.textContent = 'A stored MP4 is required. Twitch embeds cannot be normalized here.';
            }
        }

        renderDeletionDetails(selectedClip);
        const approveButton = byId('clipApproveBtn');
        const needsStorage = approved && !selectedClip.localUrl;
        approveButton.textContent = needsStorage ? 'Store published clip' : 'Approve & publish';
        setActionVisibility('clipApproveBtn', status === 0 || status === 2 || needsStorage);
        setActionVisibility('clipIgnoreBtn', status === 0);
        setActionVisibility('clipRestoreBtn', status === 1 || status === 2 || status === 3);
        setActionVisibility('clipDeleteRequestBtn', status !== 3);
        if (clipActionBusy) {
            byId('clipSelectionControls').querySelectorAll('button').forEach(button => { button.disabled = true; });
        }
    };

    const selectClip = clip => {
        if (clipActionBusy) return;
        byId('clipActionStatus').hidden = true;
        selectedClip = clip;
        groupedList.querySelectorAll('.clipAdminItem.selected').forEach(item => item.classList.remove('selected'));
        const card = Array.from(groupedList.querySelectorAll('[data-clip-id]'))
            .find(item => item.dataset.clipId === clip.id);
        card?.classList.add('selected');
        renderSelection(true);
    };

    const moveSelectedClip = (status, message) => {
        selectedClip.reviewStatus = status;
        selectedClip.enabled = status === 1;
        renderGroups(true);
        renderSelection(true);
        showNotice(message);
    };

    const reload = async () => {
        clearError();
        try {
            const body = await request();
            clips = body.clips || [];
            deletions = body.deletions || [];
            if (body.warning) showError(`Twitch could not be refreshed: ${body.warning}. Stored clips are still available.`);
            renderGroups();
        } catch (error) {
            showError(error.message);
        }
    };

    byId('clipApproveBtn').addEventListener('click', async () => {
        if (!selectedClip || clipActionBusy) return;
        const clip = selectedClip;
        const wasPublished = Number(clip.reviewStatus || 0) === 1;
        const approveButton = byId('clipApproveBtn');
        const actionStatus = byId('clipActionStatus');
        clearError();
        setClipActionBusy(true);
        approveButton.setAttribute('aria-busy', 'true');
        approveButton.textContent = 'Processing clip...';
        actionStatus.className = 'clipActionStatus';
        actionStatus.textContent = 'Downloading from Twitch, uploading to Backblaze, and normalizing audio. This may take a moment...';
        actionStatus.hidden = false;
        try {
            const body = await request({action: 'approve', clipId: clip.id, clip});
            if (body.clip) Object.assign(clip, body.clip);
            actionStatus.hidden = true;
            if (selectedClip === clip) {
                const completionMessage = body.normalizationWarning
                    ? 'The clip was stored and published with its original audio.'
                    : (wasPublished
                        ? 'The published clip was stored and its audio was processed.'
                        : 'Clip approved, stored, normalized, and published.');
                moveSelectedClip(1, completionMessage);
                if (body.normalizationWarning) {
                    showError(`The clip was stored and published, but automatic audio normalization failed: ${body.normalizationWarning}`);
                }
            } else {
                renderGroups();
            }
        } catch (error) {
            showError(error.message);
            if (selectedClip === clip) {
                actionStatus.className = 'clipActionStatus clipActionStatus--error';
                actionStatus.textContent = 'The clip was not stored. You can try again.';
            } else {
                actionStatus.hidden = true;
            }
        } finally {
            approveButton.removeAttribute('aria-busy');
            setClipActionBusy(false);
            if (selectedClip) renderSelection(false);
        }
    });

    byId('clipIgnoreBtn').addEventListener('click', async () => {
        if (!selectedClip || clipActionBusy) return;
        clearError();
        try {
            await request({action: 'ignore', clipId: selectedClip.id});
            moveSelectedClip(2, 'Clip moved to Ignored.');
        } catch (error) { showError(error.message); }
    });

    byId('clipRestoreBtn').addEventListener('click', async () => {
        if (!selectedClip || clipActionBusy) return;
        clearError();
        try {
            await request({action: 'restore', clipId: selectedClip.id});
            deletions = deletions.filter(record => record.clipId !== selectedClip.id);
            moveSelectedClip(0, 'Clip returned to Needs review.');
        } catch (error) { showError(error.message); }
    });

    byId('clipDeleteRequestBtn').addEventListener('click', async () => {
        if (!selectedClip || clipActionBusy || !window.confirm('Unpublish this clip and retain its provider links for manual deletion?')) return;
        clearError();
        try {
            const body = await request({action: 'requestDeletion', clipId: selectedClip.id});
            if (body.deletion) {
                deletions = deletions.filter(record => record.clipId !== selectedClip.id);
                deletions.push(body.deletion);
            }
            moveSelectedClip(3, 'Deletion requested. Provider links are shown with the selected clip.');
        } catch (error) { showError(error.message); }
    });

    byId('clipSaveBtn').addEventListener('click', async () => {
        if (!selectedClip || clipActionBusy) return;
        const saveButton = byId('clipSaveBtn');
        const data = {
            customTitle: byId('clipCustomTitle').value,
            favorite: byId('clipFavorite').checked,
            enabled: byId('clipEnabled').checked,
            startOffset: byId('clipStartOffset').value,
            maxDuration: byId('clipMaxDuration').value,
            playCount: byId('clipPlayCount').value
        };
        clearError();
        saveButton.disabled = true;
        saveButton.textContent = 'Saving…';
        try {
            await request({action: 'save', clipId: selectedClip.id, data});
            Object.assign(selectedClip, {
                customTitle: data.customTitle.trim() || null,
                favorite: data.favorite,
                enabled: data.enabled,
                startOffset: Number(data.startOffset || 0),
                maxDuration: Number(data.maxDuration || 0),
                playCount: Number(data.playCount || 0)
            });
            renderGroups(true);
            renderSelection(true);
            showNotice('Changes saved. The player and clip card have been refreshed.');
        } catch (error) {
            showError(error.message);
        } finally {
            saveButton.disabled = false;
            saveButton.textContent = 'Save changes';
        }
    });

    byId('clipNormalizeBtn').addEventListener('click', async () => {
        if (!selectedClip?.localUrl || selectedClip.audioNormalized || clipActionBusy) return;
        const clip = selectedClip;
        const normalizeButton = byId('clipNormalizeBtn');
        const audioStatus = byId('clipAudioStatus');
        clearError();
        setClipActionBusy(true);
        normalizeButton.textContent = 'Normalizing…';
        audioStatus.className = 'clipAudioStatus clipAudioStatus--working';
        audioStatus.textContent = 'Measuring and processing audio. Keep this page open; this may take a minute.';
        try {
            const body = await request({action: 'normalizeAudio', clipId: clip.id});
            if (body.clip) Object.assign(clip, body.clip);
            clip.audioNormalized = true;
            renderGroups(true);
            renderSelection(true);
            showNotice('Audio normalized to −16 LUFS. The player now uses the new stored copy.');
        } catch (error) {
            showError(error.message);
            audioStatus.className = 'clipAudioStatus clipAudioStatus--unavailable';
            audioStatus.textContent = 'Normalization failed. The original stored clip was not changed.';
        } finally {
            setClipActionBusy(false);
            if (selectedClip) renderSelection(false);
        }
    });

    reload();
})();
