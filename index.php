<html>

<style>

    th, td {
        padding: 5px;
        text-align: center;
    }

</style>

<form method="post" action="add_entry.php">

    <fieldset>
        <legend>Create Log</legend>

        <h3>Enter your story here:</h3>
        <textarea name="submitStoryInput" rows="10" cols="100"></textarea>
        <br/>

        <input type="submit" name="createLog" value="Submit"/>
    </fieldset>

    <div style="margin: 100px"></div>

    <fieldset>
        <legend>Create Proof of Past Log</legend>

        <input type="submit" name="createPPL" value="Create"/>
    </fieldset>

    <div style="margin: 100px"></div>

</form>


<form method="post" action="verify.php">

    <fieldset>
        <legend>Logs</legend>

        <table border="1" style="border-collapse: collapse">
            <tr>
                <th>ID</th>
                <th>Log Time</th>
                <th>Log Entry</th>
                <th>Log Chain</th>
                <th>Accumulator Entry</th>
                <th>Signature</th>
<!--                <th>AE<sub>D</sub></th>-->
                <th></th>
            </tr>

            <?php
            require_once 'engine/SecLaaSLogger.php';
            $logger = new SecLaaSLogger();
            $logs = $logger->listLogs();
            foreach ($logs as $log) {
                ?>
                <tr>
                    <td><?= $log->id ?></td>
                    <td><?= $log->logTime ?></td>
                    <td style="text-align: left">
                        <textarea name="msg<?= $log->id ?>"><?= $log->encryptedLogEntry ?></textarea>
                    </td>
                    <td><?= $log->logChain ?></td>
                    <td style="max-width: 475px; text-align: left; overflow: hidden; text-overflow: ellipsis">
                        <?= $log->accumulatorEntry ?>
                    </td>
                    <td style="max-width: 400px; text-align: left; overflow: hidden; text-overflow: ellipsis">
                        <?= $log->signature ?>
                    </td>
<!--                    <td>--><?//= $log->accumulatorEntryD ?><!--</td>-->
                    <td>
                        <input type="submit" value="Verify" name="verify<?= $log->id ?>"
                               onclick="document.getElementById('logId').value = <?= $log->id ?>"/>
                    </td>
                </tr>
            <?php } ?>
        </table>
    </fieldset>

    <input type="hidden" name="logId" id="logId" value=""/>

</form>


</html>
