<div class="clipAdminShell" id="clipAdminRoot">
    <div id="clipAdminAlert" class="adminInlineError" hidden></div>
    <div id="clipAdminNotice" class="clipAdminNotice" role="status" aria-live="polite" hidden></div>

    <div class="clipAdminToolbar">
        <div>
            <strong>Review workflow</strong>
            <span id="clipQueueBadge" class="queueBadge" data-tooltip="Number of clips still waiting for review." hidden></span>
        </div>
        <p class="clipAdminHelpNote"><span aria-hidden="true">i</span> Hover over a control for a quick explanation.</p>
    </div>

    <div class="clipAdminLayout">
        <section class="clipAdminWorkspace" aria-label="Selected clip">
            <div class="clipAdminPlayer" id="clipPlayer">
                <div class="clipAdminFallback">Select a clip to begin.</div>
            </div>

            <div class="clipAdminCurrentMeta">
                <div class="clipSelectionHeading">
                    <div>
                        <span id="clipSelectedStatus" class="clipSelectedStatus" hidden></span>
                        <strong id="clipSelectedTitle">No clip selected</strong>
                    </div>
                    <div class="clipSelectedDetails" id="clipSelectedDetails"></div>
                </div>

                <div id="clipSelectionControls" hidden>
                    <section id="clipApprovedSettings" class="clipApprovedSettings" hidden>
                        <div class="clipEditorHeading">Collection settings</div>
                        <div class="metaRow">
                            <label class="clipFieldLabel" data-tooltip="Optional title shown on Xogoria instead of the original Twitch title.">Display title<input type="text" id="clipCustomTitle" placeholder="Use original Twitch title"></label>
                        </div>
                        <div class="metaRow inline clipToggleRow">
                            <label data-tooltip="Prioritize this clip in the stream reel. Prioritized clips with fewer recorded plays are shown first."><input type="checkbox" id="clipFavorite"> Prioritize in reel</label>
                            <label data-tooltip="Include this clip on the public clips page and in the stream reel."><input type="checkbox" id="clipEnabled"> Published</label>
                        </div>
                        <div class="metaRow inline clipTimingRow">
                            <label data-tooltip="Seconds to skip from the beginning. Use 0 to start at the beginning.">Start at <input type="number" id="clipStartOffset" min="0" step="0.1" inputmode="decimal"><span>sec</span></label>
                            <label data-tooltip="Timestamp in seconds where playback stops. Use 0 to play to the end.">End at <input type="number" id="clipMaxDuration" min="0" step="0.1" inputmode="decimal"><span>sec</span></label>
                            <label data-tooltip="Recorded stream-reel plays. This helps balance how often prioritized clips are selected.">Reel plays <input type="number" id="clipPlayCount" min="0" inputmode="numeric"></label>
                        </div>
                        <div class="clipEditorButtons">
                            <button type="button" class="btnPrimary" id="clipSaveBtn" data-tooltip="Save these collection settings and refresh the player immediately.">Save changes</button>
                            <button type="button" class="btnLink" id="clipNormalizeBtn" data-tooltip="Create a consistently loud stored copy at −16 LUFS. The original remains stored as a fallback.">Normalize audio</button>
                        </div>
                        <p class="clipAudioStatus" id="clipAudioStatus"></p>
                    </section>

                    <div id="clipDeletionDetails" class="clipDeletionDetails" hidden></div>

                    <div class="clipStatusActions" aria-label="Clip status actions">
                        <button type="button" class="btnPrimary" id="clipApproveBtn" data-tooltip="Approve and immediately publish this clip, then continue with its collection settings." hidden>Approve &amp; publish</button>
                        <button type="button" class="btnLink warning" id="clipIgnoreBtn" data-tooltip="Mark this clip as reviewed without publishing it." hidden>Ignore clip</button>
                        <button type="button" class="btnLink" id="clipRestoreBtn" data-tooltip="Unpublish this clip and return it to the Needs review section." hidden>Return to needs review</button>
                        <button type="button" class="btnLink danger" id="clipDeleteRequestBtn" data-tooltip="Unpublish this clip and retain its Twitch and storage links for manual deletion." hidden>Request deletion</button>
                    </div>
                    <p class="clipActionStatus" id="clipActionStatus" role="status" aria-live="polite" hidden></p>
                </div>
            </div>
        </section>

        <aside class="clipAdminBrowser" aria-label="Clips grouped by review status">
            <div class="clipAdminListHeader">
                <span>All Twitch clips</span>
                <span class="clipListHint">Select a clip to review or edit</span>
            </div>
            <div class="clipAdminList" id="clipGroupedList"></div>
        </aside>
    </div>
</div>
