<div class="clipAdminShell" id="clipAdminRoot">
    <div id="clipAdminAlert" class="adminInlineError" hidden></div>
    <div class="clipAdminTabs">
        <button type="button" class="tabBtn active" data-clip-view="review">Review</button>
        <button type="button" class="tabBtn" data-clip-view="library">Approved collection</button>
        <button type="button" class="tabBtn" data-clip-view="deletions">Deletion list</button>
        <span id="clipQueueBadge" class="queueBadge" hidden></span>
    </div>

    <div class="clipAdminLayout" data-clip-panel="review">
        <div class="clipAdminLeft">
            <div class="clipAdminPlayer" id="clipReviewPlayer"><div class="clipAdminFallback">Select a Twitch clip to review.</div></div>
            <div class="clipAdminCurrentMeta">
                <strong id="clipReviewTitle">No clip selected</strong>
                <div class="metaRow" id="clipReviewDetails"></div>
                <div class="metaRow formActions">
                    <button type="button" class="btnPrimary" id="clipApproveBtn" disabled>Approve for collection</button>
                    <button type="button" class="btnLink" id="clipIgnoreBtn" disabled>Ignore</button>
                    <button type="button" class="btnLink danger" id="clipDeleteRequestBtn" disabled>Request deletion</button>
                </div>
            </div>
        </div>
        <div class="clipAdminRight">
            <div class="clipAdminListHeader"><span>Recent Twitch clips</span><select id="clipReviewFilter"><option value="pending">Pending</option><option value="ignored">Ignored</option><option value="deletion">Deletion requested</option><option value="all">All</option></select></div>
            <div class="clipAdminList" id="clipReviewList"></div>
        </div>
    </div>

    <div class="clipAdminLayout" data-clip-panel="library" hidden>
        <div class="clipAdminLeft">
            <div class="clipAdminPlayer" id="clipLibraryPlayer"><div class="clipAdminFallback">Select an approved clip.</div></div>
            <div class="clipAdminCurrentMeta">
                <div class="metaRow"><label>Custom title<input type="text" id="clipCustomTitle"></label></div>
                <div class="metaRow inline"><label><input type="checkbox" id="clipFavorite"> Favorite</label><label><input type="checkbox" id="clipEnabled"> Show in collection</label></div>
                <div class="metaRow inline"><label>Start <input type="number" id="clipStartOffset" min="0" step="0.1"></label><label>End <input type="number" id="clipMaxDuration" min="0" step="0.1"></label><label>Plays <input type="number" id="clipPlayCount" min="0"></label></div>
                <div class="metaRow formActions"><button type="button" class="btnPrimary" id="clipSaveBtn" disabled>Save metadata</button><button type="button" class="btnLink danger" id="clipLibraryDeleteBtn" disabled>Request deletion</button></div>
            </div>
        </div>
        <div class="clipAdminRight"><div class="clipAdminListHeader">Approved clips</div><div class="clipAdminList" id="clipLibraryList"></div></div>
    </div>

    <div data-clip-panel="deletions" hidden>
        <p>Deletion requests are retained here because Twitch does not provide a supported broadcaster clip-delete API. Backblaze links are retained for manual deletion when an automatic file identifier is unavailable.</p>
        <div class="clipDeletionList" id="clipDeletionList"></div>
    </div>
</div>
