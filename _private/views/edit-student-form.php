<div id="addStudentCard">
    <h2>Editovat záznam student</h2>
    <?php if (!empty($studentAddError)): ?>
        <p style="color:red;"><?= htmlspecialchars($studentAddError) ?></p>
    <?php endif; ?>
    <form class="formEdit" method="POST">
                <?= csrf_field() ?>
        <input type="hidden" name="edit_student" value="1" />
        <input type="hidden" name="student_id" value="<?= htmlspecialchars(
            $selectedStudentId,
        ) ?>" />
        <fieldset>
        <div class="formRow">
        <label for="name">Name:</label><input type="text" id="name" name="name" value="<?= htmlspecialchars(
            $selectedStudentName,
        ) ?>" required />
        </div>
        <div class="formRow">
        <label for="email">Email:</label><input type="text" id="email" name="email" value="<?= htmlspecialchars(
            $selectedStudentEmail,
        ) ?>" required />
        </div>
        </fieldset>
        <fieldset>

        <div class="formRow">
        <label for="uco">Student ID:</label><input type="text" name="uco" id="uco" value="<?= htmlspecialchars(
            $selectedStudentUCO,
        ) ?>" />
        </div>
        <div class="formRow">
        <label for="degree">Studium:</label>

        <select id="degree" name="degree" required>
            <option value="bc" <?= $selectedStudentDegree === 'bc' ? 'selected' : '' ?>>Bakalářské</option>
            <option value="mgr" <?= $selectedStudentDegree === 'mgr' ? 'selected' : '' ?>>Magisterské</option>
            <option value="phd" <?= $selectedStudentDegree === 'phd' ? 'selected' : '' ?>>Doktorské</option>
            <option value="jiná" <?= $selectedStudentDegree === 'jiná' ? 'selected' : '' ?>>Jiné</option>
        </select>

        </div>
        <div class="formRow">
        <label for="uni">University:</label>

            <select id="uni" name="uni" required>
                <option value="muni" <?= $selectedStudentUni === 'muni' ? 'selected' : '' ?>>Masarykova university</option>
                <option value="jamu" <?= $selectedStudentUni === 'jamu' ? 'selected' : '' ?>>Janáčkova akademie múzických umění</option>
                <option value="upol" <?= $selectedStudentUni === 'upol' ? 'selected' : '' ?>>University Palackého v Olomouci</option>
                <option value="jiná" <?= $selectedStudentUni === 'jiná' ? 'selected' : '' ?>>Jiná</option>
            </select>
        </div>
        </fieldset>
        <fieldset>
        <div class="formRow"><label for="prace">Name práce:</label>
            <input type="text" id="prace" name="prace" value="<?= htmlspecialchars(
                $selectedStudentPrace,
            ) ?>" required />
        </div>
        <div class="formRow">
        <label for="semestr">Semestr:</label>
            <input value="<?= htmlspecialchars(
                $selectedStudentSemestr,
            ) ?>" type="number" name="semestr" id="semestr" required />
        </div>

        </fieldset>
        <div class="formRow">
        <button type="submit" class="button-link">💾 Save změny</button>
        <a href="?" class="button-link">🚫 Cancel</a>
        </div>
    </form>
</div>
