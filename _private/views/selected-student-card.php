<div id="studentCard">
    <section class="standardCard" style="border-left: 4px solid <?= $selectedStudentActive ? 'var(--success-color)' : 'var(--alert-color)' ?>;">
        <div class="student-title-row">
            <div>
                <h2 class="text-font-bold" style="margin-bottom: 0.2rem;">
                    <?= htmlspecialchars($selectedStudentName) ?>
                </h2>
                <p class="muted" style="margin: 0;">
                    <?= $universityLabel ?>, <?= $degreeLabel ?> | <?= h($selectedStudentSemestr) ?>. semestr
                    <form method="post" style="display:inline;margin-left:.5rem"><?= csrf_field() ?><input type="hidden" name="increment_semester" value="1"><input type="hidden" name="student_id" value="<?= (int)$selectedStudentId ?>"><button type="submit" class="button-link subtle" title="Zvýšit semestr">＋ semestr</button></form>
                </p>
            </div>

            <div>
                <span class="status-pill <?= $selectedStudentActive ? '' : 'inactive' ?>">
                    <?= $selectedStudentActive ? '● active' : '● neactive' ?>
                </span>
            </div>
        </div>

        <div class="info-grid" style="margin-top: 1.25rem;">
            <?php if ((string)$selectedStudentUCO !== '0' && trim((string)$selectedStudentUCO) !== ''): ?><div class="info-item">
                <span class="info-label muted code-font">Student ID / IS</span>
                <div class="info-value text-font">
                    <?= htmlspecialchars($selectedStudentUCO) ?>
                    <?php
                        $uniUrl = ($selectedStudentUni === "muni") ? "is.muni.cz" : (($selectedStudentUni === "jamu") ? "is.jamu.cz" : "");
                    ?>
                    <?php if ($uniUrl && !empty($selectedStudentUCO)): ?>
                        <div style="margin-top: 0.4rem;">
                            <a href="https://<?= $uniUrl ?>/auth/osoba/<?= htmlspecialchars($selectedStudentUCO) ?>" target="_blank" rel="noopener noreferrer">
                                Otevřít v IS
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div><?php endif; ?>

            <div class="info-item">
                <span class="info-label muted code-font">Email</span>
                <div class="info-value text-font">
                    <a href="mailto:<?= htmlspecialchars($selectedStudentEmail) ?>">
                        <?= htmlspecialchars($selectedStudentEmail) ?>
                    </a>
                    <div style="margin-top: 0.4rem;">
                        <button type="button" class="button-link subtle" data-copy="<?= h($selectedStudentEmail) ?>">
                            📋 Kopírovat e-mail
                        </button>
                    </div>
                </div>
            </div>

            <div class="info-item" style="grid-column: 1 / -1;">
                <span class="info-label muted code-font">Name práce</span>
                <div class="info-value text-font-bold" style="font-size: 1.08rem;">
                    <?= htmlspecialchars($selectedStudentPrace) ?>
                </div>
            </div>
        </div>

        <div class="form-actions" style="margin-top: 1.5rem;">
            <a href="?student_id=<?= $selectedStudentId ?>&add_consultation=1" class="button-link primary">➕ Add konzultaci</a>
            <a href="?edit_student_form=1&student_id=<?= $selectedStudentId ?>" class="button-link">🔧 Edit</a>

            <form method="POST" style="margin:0;">
                <?= csrf_field() ?>
                <input type="hidden" name="<?= $selectedStudentActive ? 'deactivate_id' : 'activate_id' ?>" value="<?= $selectedStudentId ?>">
                <button type="submit" class="button-link">
                    <?= $selectedStudentActive ? "🚫 Deactivate" : "✅ Activate" ?>
                </button>
            </form>

            <a href="?" class="button-link subtle">Zavřít záznam</a>
        </div>
    </section>

    <?php if (($selectedStudentDegree ?? '') === 'phd'): ?>
    <section class="standardCard">
        <h3 class="text-font-bold">PhD přehled</h3>
        <?php if ($selectedDiaryEntry): ?>
            <details>
                <summary><strong>Latest deníček — <?= htmlspecialchars(date('j. n. Y', strtotime($selectedDiaryEntry['week_start']))) ?></strong></summary>
                <div class="info-item" style="margin-top:1rem;">
                    <p><?= nl2br(htmlspecialchars($selectedDiaryEntry['what_did_you_do'] ?? '—')) ?></p>
                    <?php if (!empty($selectedDiaryEntry['notes_to_teacher']) && !empty($selectedDiaryEntry['private_notes'])): ?>
                        <p><strong>Note od student:</strong><br><?= nl2br(htmlspecialchars($selectedDiaryEntry['private_notes'])) ?></p>
                    <?php endif; ?>
                    <p><strong>Feedback:</strong> <?= !empty($selectedDiaryEntry['teacher_feedback']) ? nl2br(htmlspecialchars($selectedDiaryEntry['teacher_feedback'])) : 'čeká na odpověď' ?></p>
                </div>
            </details>
        <?php else: ?><p class="muted">No nebyl odeslán no deníček.</p><?php endif; ?>
        <p><a class="button-link" href="diaries/dashboard_teacher.php?student_id=<?= (int)$selectedStudentId ?><?= $selectedDiaryEntry ? '&entry_id=' . (int)$selectedDiaryEntry['id'] : '' ?>">📔 Management deníčků</a></p>

        <h4 class="text-font-bold" style="margin-top:1.5rem;">Publications</h4>
        <?php if (!$selectedPublications): ?><p class="muted">No publikace.</p>
        <?php else: foreach ($selectedPublications as $pub):
            $steps = ['not_started'=>0,'data_collection'=>20,'draft'=>40,'submitted'=>60,'review'=>80,'accepted'=>100,'printed'=>100];
            $pct = $steps[$pub['status'] ?? 'not_started'] ?? 0;
        ?>
            <div class="info-item" style="margin:.6rem 0;">
                <strong><?= htmlspecialchars($pub['title'] ?: '(untitled)') ?></strong> <span class="muted">(<?= htmlspecialchars($pub['type'] ?: 'typ neuveden') ?>)</span>
                <div style="height:8px;background:#e5e7eb;border-radius:5px;margin-top:.5rem"><div style="height:100%;width:<?= $pct ?>%;background:var(--success-color);border-radius:5px"></div></div>
                <small class="muted"><?= htmlspecialchars($pub['status'] ?: 'neuvedeno') ?> · <?= $pct ?> %</small>
            </div>
        <?php endforeach; endif; ?>
    </section>
    <?php endif; ?>

    <section class="standardCard">
        <h3 class="text-font-bold">History consultations</h3>

        <?php if (count($consultations) > 0): ?>
            <div class="table-responsive">
                <table class="schedulerTable code-font">
                    <thead>
                        <tr>
                            <th style="width: 140px;">Date</th>
                            <th>Úkoly / Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($consultations as $c): ?>
                            <tr
                                class="table-row-link"
                                onclick="window.location='?student_id=<?= $selectedStudentId ?>&consultation_id=<?= $c['id'] ?>'"
                                title="Zobrazit detail"
                            >
                                <td class="text-font-bold" style="white-space: nowrap;">
                                    <?= date("j. n. Y", strtotime($c["date"])) ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($c["tasks"]) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alertBox">⚠️ No consultations not foundy.</div>
        <?php endif; ?>
    </section>

    <?php if ($consultationDetail): ?>
    <section class="standardCard">
        <h2 class="text-font-bold">Detail consultations</h2>
        <p class="code-font muted" style="margin-bottom: 1rem;">
            Date: <?= htmlspecialchars($consultationDetail["date"]) ?>
        </p>

        <div style="margin-bottom: 1.25rem;">
            <h4 class="muted code-font" style="font-size: 0.8rem; text-transform: uppercase;">Content</h4>
            <div class="text-font info-item" style="background: #fff;">
                <?= nl2br(htmlspecialchars($consultationDetail["content"])) ?>
            </div>
        </div>

        <div>
            <h4 class="muted code-font" style="font-size: 0.8rem; text-transform: uppercase;">Úkoly</h4>
            <div class="code-font info-item" style="background: #fffdf7;">
                <?= nl2br(htmlspecialchars($consultationDetail["tasks"])) ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($showFormConsultation): ?>
    <section class="standardCard" id="formSection">
        <form class="formEdit text-font" method="POST">
                <?= csrf_field() ?>
            <h3 class="text-font-bold">Nová consultations</h3>
            <input type="hidden" name="add_consultation" value="1">
            <input type="hidden" name="student_id" value="<?= $selectedStudentId ?>" />

            <div class="formRow" style="margin-top: 1rem;">
                <label for="content" class="code-font">Content consultations</label>
                <textarea id="content" name="content" rows="10" required></textarea>
            </div>

            <div class="formRow">
                <label for="tasks" class="code-font">Úkoly do příště</label>
                <textarea id="tasks" name="tasks" rows="4" required>No.</textarea>
            </div>

            <div class="form-actions">
                <button type="submit" class="button-link primary">📥 Save záznam</button>
                <a href="?student_id=<?= $selectedStudentId ?>" class="button-link red">🚫 Cancel</a>
            </div>
        </form>
    </section>
    <?php endif; ?>
</div>

<script>
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        alert('Email zkopírován do schránky.');
    });
}
</script>
