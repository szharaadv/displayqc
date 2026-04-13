<?php
session_start();
include '../config/koneksi.php';

if (!isset($_SESSION['id'])) {
    header("Location: ../auth/login.php");
    exit;
}

$parts = mysqli_query($conn, "SELECT * FROM master_parts ORDER BY category, part_name ASC");
$lines = mysqli_query($conn, "SELECT * FROM master_lines ORDER BY category, catalog_line ASC");
$machines = mysqli_query($conn, "SELECT * FROM master_machines ORDER BY category, machine_jig_catalog ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Order</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
    <div class="container">
        <h2>Create Order Sampling</h2>

        <form action="simpan_order.php" method="POST">
            <label>Category</label>
            <select name="category" id="category" required>
                <option value="">-- Pilih Category --</option>
                <option value="CONROD">CONROD</option>
                <option value="MS1">MS1</option>
                <option value="MS2">MS2</option>
            </select>

            <label>Part Name</label>
            <select name="part_id" id="part_id" required>
                <option value="">-- Pilih Part Name --</option>
                <?php while($p = mysqli_fetch_assoc($parts)): ?>
                    <option
                        value="<?php echo $p['id']; ?>"
                        data-category="<?php echo $p['category']; ?>"
                        data-partno="<?php echo htmlspecialchars($p['part_no']); ?>">
                        <?php echo htmlspecialchars($p['part_name']); ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <label>Part Number</label>
            <input type="text" id="part_no_display" readonly placeholder="Part number akan muncul otomatis">

            <label>Catalog Line</label>
            <select name="line_id" id="line_id" required>
                <option value="">-- Pilih Line --</option>
                <?php while($l = mysqli_fetch_assoc($lines)): ?>
                    <option
                        value="<?php echo $l['id']; ?>"
                        data-category="<?php echo $l['category']; ?>">
                        <?php echo htmlspecialchars($l['catalog_line']); ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <label>Machine No / Jig Catalog</label>
            <select name="machine_id" id="machine_id" required>
                <option value="">-- Pilih Machine --</option>
                <?php while($m = mysqli_fetch_assoc($machines)): ?>
                    <option
                        value="<?php echo $m['id']; ?>"
                        data-category="<?php echo $m['category']; ?>">
                        <?php echo htmlspecialchars($m['machine_jig_catalog']); ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <label>Qty</label>
            <input type="number" name="qty" value="1" min="1" required>

            <div style="margin-top: 18px;">
                <button type="submit">Simpan Order</button>
            </div>

            <br>
            <a class="btn" href="../menu.php">Kembali ke Menu</a>
        </form>
    </div>

    <script>
        const categorySelect = document.getElementById('category');
        const partSelect = document.getElementById('part_id');
        const lineSelect = document.getElementById('line_id');
        const machineSelect = document.getElementById('machine_id');
        const partNoDisplay = document.getElementById('part_no_display');

        function filterOptions(selectElement, category) {
            const options = selectElement.querySelectorAll('option');

            options.forEach((option, index) => {
                if (index === 0) {
                    option.hidden = false;
                    return;
                }

                const optionCategory = option.getAttribute('data-category');
                option.hidden = optionCategory !== category;
            });

            selectElement.value = '';
        }

        function updatePartNumber() {
            const selectedOption = partSelect.options[partSelect.selectedIndex];
            if (selectedOption && selectedOption.value !== '') {
                partNoDisplay.value = selectedOption.getAttribute('data-partno') || '';
            } else {
                partNoDisplay.value = '';
            }
        }

        categorySelect.addEventListener('change', function () {
            const selectedCategory = this.value;

            filterOptions(partSelect, selectedCategory);
            filterOptions(lineSelect, selectedCategory);
            filterOptions(machineSelect, selectedCategory);

            partNoDisplay.value = '';
        });

        partSelect.addEventListener('change', updatePartNumber);

        window.addEventListener('load', function () {
            filterOptions(partSelect, '');
            filterOptions(lineSelect, '');
            filterOptions(machineSelect, '');
            partNoDisplay.value = '';
        });
    </script>
</body>
</html>