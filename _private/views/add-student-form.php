<div id="addStudentCard">
    <h2>Add nového student</h2>
    <?php if (!empty($studentAddError)): ?>
        <p style="color:red;"><?= htmlspecialchars($studentAddError) ?></p>
    <?php endif; ?>
    <form class="formEdit" method="POST">
                <?= csrf_field() ?>
        <input type="hidden" name="add_student" value="1" />
        <fieldset>
        <div class="formRow">
        <label for="name">Name:</label><input type="text" id="name" name="name" required />
        </div>
        <div class="formRow">
        <label for="email">Email:</label><input type="text" id="email" name="email" required />
        </div>
        </fieldset>
        <fieldset>

        <div class="formRow">
        <label for="uco">Student ID:</label><input type="text" name="uco" id="uco" value="0" />
        </div>
        <div class="formRow">
        <label for="degree">Studium:</label>

        <select id="degree" name="degree" required>
            <option value="bc" selected>Bakalářské</option>
            <option value="mgr">Magisterské</option>
            <option value="phd">Doktorské</option>
            <option value="jiná">Jiné</option>
        </select>

        </div>
        <div class="formRow">
        <label for="uni">University:</label>

            <select id="uni" name="uni" required>
                <option value="muni" selected>Masarykova university</option>
                <option value="jamu">Janáčkova akademie múzických umění</option>
                <option value="upol">University Palackého v Olomouci</option>
                <option value="jiná">Jiná</option>
            </select>
        </div>
        </fieldset>
        <fieldset>
        <div class="formRow"><label for="prace">Name práce:</label>
            <input type="text" id="prace" name="prace" required />
        </div>
        <div class="formRow">
        <label for="semestr">Semestr:</label>
            <input type="text" name="semestr" id="semestr" value="0" required />
        </div>

        </fieldset>
        <div class="formRow">
        <button type="submit" class="button-link">🎓 Add záznam</button>
        <a href="?" class="button-link">🚫 Cancel</a>
        </div>
    </form>
</div>
