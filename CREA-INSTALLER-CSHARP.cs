using System;
using System.IO;
using System.Net;
using System.Diagnostics;
using System.Windows.Forms;
using System.IO.Compression;
using System.Threading.Tasks;
using System.Security.Principal;

namespace FPDMSInstaller
{
    public partial class InstallerForm : Form
    {
        private ProgressBar progressBar;
        private Label statusLabel;
        private Label titleLabel;
        private Button installButton;
        private Button cancelButton;
        private string installPath;

        public InstallerForm()
        {
            InitializeComponent();
            installPath = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.Desktop), "FP-Digital-Marketing-Suite");
        }

        private void InitializeComponent()
        {
            this.Text = "FP Digital Marketing Suite - Installer";
            this.Size = new System.Drawing.Size(500, 400);
            this.StartPosition = FormStartPosition.CenterScreen;
            this.FormBorderStyle = FormBorderStyle.FixedDialog;
            this.MaximizeBox = false;
            this.MinimizeBox = false;

            // Title
            titleLabel = new Label();
            titleLabel.Text = "FP Digital Marketing Suite";
            titleLabel.Font = new System.Drawing.Font("Segoe UI", 16, System.Drawing.FontStyle.Bold);
            titleLabel.ForeColor = System.Drawing.Color.FromArgb(102, 126, 234);
            titleLabel.Size = new System.Drawing.Size(400, 40);
            titleLabel.Location = new System.Drawing.Point(50, 20);
            titleLabel.TextAlign = System.Drawing.ContentAlignment.MiddleCenter;
            this.Controls.Add(titleLabel);

            // Subtitle
            Label subtitleLabel = new Label();
            subtitleLabel.Text = "Installer Professionale v1.0.0";
            subtitleLabel.Font = new System.Drawing.Font("Segoe UI", 10);
            subtitleLabel.ForeColor = System.Drawing.Color.Gray;
            subtitleLabel.Size = new System.Drawing.Size(400, 25);
            subtitleLabel.Location = new System.Drawing.Point(50, 60);
            subtitleLabel.TextAlign = System.Drawing.ContentAlignment.MiddleCenter;
            this.Controls.Add(subtitleLabel);

            // Status
            statusLabel = new Label();
            statusLabel.Text = "Pronto per l'installazione";
            statusLabel.Font = new System.Drawing.Font("Segoe UI", 9);
            statusLabel.Size = new System.Drawing.Size(400, 25);
            statusLabel.Location = new System.Drawing.Point(50, 120);
            statusLabel.TextAlign = System.Drawing.ContentAlignment.MiddleCenter;
            this.Controls.Add(statusLabel);

            // Progress Bar
            progressBar = new ProgressBar();
            progressBar.Size = new System.Drawing.Size(400, 25);
            progressBar.Location = new System.Drawing.Point(50, 150);
            progressBar.Style = ProgressBarStyle.Continuous;
            this.Controls.Add(progressBar);

            // Install Path
            Label pathLabel = new Label();
            pathLabel.Text = $"Installazione in: {installPath}";
            pathLabel.Font = new System.Drawing.Font("Segoe UI", 8);
            pathLabel.ForeColor = System.Drawing.Color.Gray;
            pathLabel.Size = new System.Drawing.Size(400, 20);
            pathLabel.Location = new System.Drawing.Point(50, 190);
            pathLabel.TextAlign = System.Drawing.ContentAlignment.MiddleCenter;
            this.Controls.Add(pathLabel);

            // Features
            Label featuresLabel = new Label();
            featuresLabel.Text = "✓ Download automatico PHP Desktop\n✓ Configurazione automatica\n✓ Database SQLite embedded\n✓ Interfaccia moderna";
            featuresLabel.Font = new System.Drawing.Font("Segoe UI", 9);
            featuresLabel.Size = new System.Drawing.Size(400, 80);
            featuresLabel.Location = new System.Drawing.Point(50, 220);
            featuresLabel.TextAlign = System.Drawing.ContentAlignment.MiddleLeft;
            this.Controls.Add(featuresLabel);

            // Install Button
            installButton = new Button();
            installButton.Text = "Installa";
            installButton.Font = new System.Drawing.Font("Segoe UI", 10, System.Drawing.FontStyle.Bold);
            installButton.BackColor = System.Drawing.Color.FromArgb(102, 126, 234);
            installButton.ForeColor = System.Drawing.Color.White;
            installButton.Size = new System.Drawing.Size(120, 35);
            installButton.Location = new System.Drawing.Point(200, 320);
            installButton.Click += InstallButton_Click;
            this.Controls.Add(installButton);

            // Cancel Button
            cancelButton = new Button();
            cancelButton.Text = "Annulla";
            cancelButton.Font = new System.Drawing.Font("Segoe UI", 10);
            cancelButton.Size = new System.Drawing.Size(80, 35);
            cancelButton.Location = new System.Drawing.Point(330, 320);
            cancelButton.Click += (s, e) => this.Close();
            this.Controls.Add(cancelButton);
        }

        private async void InstallButton_Click(object sender, EventArgs e)
        {
            installButton.Enabled = false;
            cancelButton.Enabled = false;
            progressBar.Value = 0;

            try
            {
                await InstallApplication();
                MessageBox.Show("Installazione completata con successo!\n\nL'applicazione è stata installata sul desktop.\n\nCredenziali di accesso:\nEmail: admin@localhost\nPassword: admin123", 
                    "Installazione Completata", MessageBoxButtons.OK, MessageBoxIcon.Information);
                this.Close();
            }
            catch (Exception ex)
            {
                MessageBox.Show($"Errore durante l'installazione:\n\n{ex.Message}", 
                    "Errore", MessageBoxButtons.OK, MessageBoxIcon.Error);
                installButton.Enabled = true;
                cancelButton.Enabled = true;
            }
        }

        private async Task InstallApplication()
        {
            // Step 1: Create directory
            UpdateStatus("Creazione directory di installazione...", 10);
            if (Directory.Exists(installPath))
            {
                Directory.Delete(installPath, true);
            }
            Directory.CreateDirectory(installPath);

            // Step 2: Download PHP Desktop
            UpdateStatus("Download PHP Desktop...", 20);
            string phpDesktopUrl = "https://github.com/cztomczak/phpdesktop/releases/download/v57.0/phpdesktop-chrome-57.0-msvc-php-7.4.zip";
            string zipFile = Path.Combine(Path.GetTempPath(), "phpdesktop.zip");
            
            using (var client = new WebClient())
            {
                client.DownloadProgressChanged += (s, e) =>
                {
                    int progress = 20 + (int)(e.ProgressPercentage * 0.4); // 20-60%
                    progressBar.Value = progress;
                };
                
                await client.DownloadFileTaskAsync(phpDesktopUrl, zipFile);
            }

            // Step 3: Extract PHP Desktop
            UpdateStatus("Estrazione PHP Desktop...", 60);
            ZipFile.ExtractToDirectory(zipFile, installPath);
            File.Delete(zipFile);

            // Rename executable
            string oldExe = Path.Combine(installPath, "phpdesktop-chrome.exe");
            string newExe = Path.Combine(installPath, "FP-DMS.exe");
            if (File.Exists(oldExe))
            {
                File.Move(oldExe, newExe);
            }

            // Step 4: Create application structure
            UpdateStatus("Creazione struttura applicazione...", 70);
            Directory.CreateDirectory(Path.Combine(installPath, "www", "public"));
            Directory.CreateDirectory(Path.Combine(installPath, "www", "public", "storage", "logs"));
            Directory.CreateDirectory(Path.Combine(installPath, "www", "public", "storage", "uploads"));
            Directory.CreateDirectory(Path.Combine(installPath, "www", "public", "storage", "cache"));

            // Step 5: Create application files
            UpdateStatus("Creazione file applicazione...", 80);
            CreateApplicationFiles();

            // Step 6: Create settings.json
            UpdateStatus("Configurazione PHP Desktop...", 90);
            CreateSettingsFile();

            // Step 7: Create launcher
            UpdateStatus("Creazione launcher...", 95);
            CreateLauncher();

            // Step 8: Create desktop shortcut
            UpdateStatus("Creazione collegamento desktop...", 100);
            CreateDesktopShortcut();

            UpdateStatus("Installazione completata!", 100);
        }

        private void CreateApplicationFiles()
        {
            string indexFile = Path.Combine(installPath, "www", "public", "index.php");
            string indexContent = @"<?php
session_start();
$config = ['app_name' => 'FP Digital Marketing Suite', 'version' => '1.0.0', 'db_file' => __DIR__ . '/../storage/database.sqlite'];
if (!file_exists($config['db_file'])) {
    $pdo = new PDO('sqlite:' . $config['db_file']);
    $pdo->exec('CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(255) NOT NULL, email VARCHAR(255) UNIQUE NOT NULL, password VARCHAR(255) NOT NULL, role VARCHAR(50) DEFAULT ''admin'', created_at DATETIME DEFAULT CURRENT_TIMESTAMP); CREATE TABLE IF NOT EXISTS clients (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(255) NOT NULL, email_to TEXT, logo_url TEXT, timezone VARCHAR(64) DEFAULT ''Europe/Rome'', created_at DATETIME DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP); CREATE TABLE IF NOT EXISTS reports (id INTEGER PRIMARY KEY AUTOINCREMENT, client_id INTEGER, title VARCHAR(255) NOT NULL, content TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (client_id) REFERENCES clients (id));');
    $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
    $pdo->exec('INSERT OR IGNORE INTO users (name, email, password, role) VALUES (''Administrator'', ''admin@localhost'', ''' . $adminPassword . ''', ''admin'')');
}
if (!isset($_SESSION['user_id'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
        $email = $_POST['email'] ?? ''; $password = $_POST['password'] ?? '';
        $pdo = new PDO('sqlite:' . $config['db_file']); $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?'); $stmt->execute([$email]); $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password'])) { $_SESSION['user_id'] = $user['id']; $_SESSION['user_name'] = $user['name']; $_SESSION['user_email'] = $user['email']; header('Location: /'); exit; } else { $error = 'Credenziali non valide'; }
    }
?>
<!DOCTYPE html><html lang='it'><head><meta charset='UTF-8'><title><?= $config['app_name'] ?> - Login</title><style>body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; } .login-container { background: white; padding: 40px; border-radius: 10px; box-shadow: 0 15px 35px rgba(0,0,0,0.1); width: 100%; max-width: 400px; } .logo { text-align: center; margin-bottom: 30px; } .logo h1 { color: #667eea; font-size: 24px; margin-bottom: 5px; } .logo p { color: #666; font-size: 14px; } .form-group { margin-bottom: 20px; } label { display: block; margin-bottom: 5px; color: #333; font-weight: 500; } input { width: 100%; padding: 12px; border: 2px solid #e1e5e9; border-radius: 6px; font-size: 14px; transition: border-color 0.3s; } input:focus { outline: none; border-color: #667eea; } button { width: 100%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 12px; border: none; border-radius: 6px; font-size: 16px; font-weight: 500; cursor: pointer; transition: transform 0.2s; } button:hover { transform: translateY(-2px); } .error { background: #fee; color: #c33; padding: 10px; border-radius: 4px; margin-bottom: 15px; border-left: 4px solid #c33; } .default-credentials { background: #f0f8ff; padding: 15px; border-radius: 6px; margin-top: 20px; border-left: 4px solid #667eea; } .default-credentials h4 { color: #667eea; margin-bottom: 10px; } .default-credentials p { color: #666; font-size: 14px; line-height: 1.4; }</style></head><body><div class='login-container'><div class='logo'><h1>FP DMS</h1><p>Digital Marketing Suite</p></div><?php if (isset($error)): ?><div class='error'><?= htmlspecialchars($error) ?></div><?php endif; ?><form method='POST'><input type='hidden' name='action' value='login'><div class='form-group'><label>Email</label><input type='email' name='email' value='admin@localhost' required></div><div class='form-group'><label>Password</label><input type='password' name='password' value='admin123' required></div><button type='submit'>Accedi</button></form><div class='default-credentials'><h4>Credenziali di Default</h4><p>Email: admin@localhost<br>Password: admin123</p><p><strong>Importante:</strong> Cambia queste credenziali dopo il primo accesso!</p></div></div></body></html>
<?php exit; }
if (isset($_GET['logout'])) { session_destroy(); header('Location: /'); exit; }
$pdo = new PDO('sqlite:' . $config['db_file']); $clientsCount = $pdo->query('SELECT COUNT(*) FROM clients')->fetchColumn(); $reportsCount = $pdo->query('SELECT COUNT(*) FROM reports')->fetchColumn(); $usersCount = $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
?>
<!DOCTYPE html><html lang='it'><head><meta charset='UTF-8'><title><?= $config['app_name'] ?> - Dashboard</title><style>* { margin: 0; padding: 0; box-sizing: border-box; } body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f8f9fa; color: #333; } .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); } .header-content { max-width: 1200px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; } .logo h1 { font-size: 24px; margin-bottom: 5px; } .logo p { opacity: 0.9; font-size: 14px; } .user-info { text-align: right; } .user-info p { margin-bottom: 5px; } .btn { background: rgba(255,255,255,0.2); color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px; border: 1px solid rgba(255,255,255,0.3); transition: background 0.3s; } .btn:hover { background: rgba(255,255,255,0.3); } .container { max-width: 1200px; margin: 30px auto; padding: 0 20px; } .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px; } .stat-card { background: white; padding: 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); text-align: center; } .stat-number { font-size: 36px; font-weight: bold; color: #667eea; margin-bottom: 10px; } .stat-label { color: #666; font-size: 14px; text-transform: uppercase; letter-spacing: 1px; } .features-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; } .feature-card { background: white; padding: 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); } .feature-card h3 { color: #667eea; margin-bottom: 15px; } .feature-card p { color: #666; margin-bottom: 20px; line-height: 1.6; } .feature-btn { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 10px 20px; text-decoration: none; border-radius: 6px; display: inline-block; transition: transform 0.2s; } .feature-btn:hover { transform: translateY(-2px); } .system-info { background: white; padding: 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-top: 30px; } .system-info h3 { color: #667eea; margin-bottom: 15px; } .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; } .info-item { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #eee; } .info-label { font-weight: 500; color: #333; } .info-value { color: #666; }</style></head><body><div class='header'><div class='header-content'><div class='logo'><h1><?= $config['app_name'] ?></h1><p>Portable Edition v<?= $config['version'] ?></p></div><div class='user-info'><p>Benvenuto, <?= htmlspecialchars($_SESSION['user_name']) ?></p><p><?= htmlspecialchars($_SESSION['user_email']) ?></p><a href='?logout=1' class='btn'>Logout</a></div></div></div><div class='container'><div class='stats-grid'><div class='stat-card'><div class='stat-number'><?= $clientsCount ?></div><div class='stat-label'>Clienti</div></div><div class='stat-card'><div class='stat-number'><?= $reportsCount ?></div><div class='stat-label'>Report</div></div><div class='stat-card'><div class='stat-number'><?= $usersCount ?></div><div class='stat-label'>Utenti</div></div></div><div class='features-grid'><div class='feature-card'><h3>Gestione Clienti</h3><p>Gestisci i tuoi clienti, le loro informazioni e i progetti di marketing digitale.</p><a href='#' class='feature-btn'>Gestisci Clienti</a></div><div class='feature-card'><h3>Report e Analytics</h3><p>Crea report dettagliati e analisi per i tuoi clienti con grafici e statistiche.</p><a href='#' class='feature-btn'>Crea Report</a></div><div class='feature-card'><h3>Configurazione</h3><p>Configura le impostazioni dell'applicazione, utenti e preferenze del sistema.</p><a href='#' class='feature-btn'>Configura</a></div></div><div class='system-info'><h3>Informazioni Sistema</h3><div class='info-grid'><div class='info-item'><span class='info-label'>Versione</span><span class='info-value'><?= $config['version'] ?></span></div><div class='info-item'><span class='info-label'>Modalità</span><span class='info-value'>Portable</span></div><div class='info-item'><span class='info-label'>Database</span><span class='info-value'>SQLite</span></div><div class='info-item'><span class='info-label'>PHP Version</span><span class='info-value'><?= PHP_VERSION ?></span></div></div></div></div></body></html>";

            File.WriteAllText(indexFile, indexContent);
        }

        private void CreateSettingsFile()
        {
            string settingsFile = Path.Combine(installPath, "settings.json");
            string settingsContent = @"{
    ""title"": ""FP Digital Marketing Suite"",
    ""main_window"": {
        ""default_size"": [1200, 800],
        ""minimum_size"": [800, 600],
        ""center_on_screen"": true,
        ""start_maximized"": false
    },
    ""web_server"": {
        ""listen_on"": [""127.0.0.1"", 8080],
        ""www_directory"": ""www/public"",
        ""index_files"": [""index.php""],
        ""cgi_interpreter"": ""php/php-cgi.exe"",
        ""cgi_extensions"": [""php""]
    },
    ""chrome"": {
        ""cache_path"": ""webcache"",
        ""context_menu"": {
            ""enable_menu"": false
        }
    },
    ""application"": {
        ""hide_php_console"": true
    }
}";

            File.WriteAllText(settingsFile, settingsContent);
        }

        private void CreateLauncher()
        {
            string launcherFile = Path.Combine(installPath, "AVVIA-APPLICAZIONE.bat");
            string launcherContent = @"@echo off
title FP Digital Marketing Suite
echo Avvio applicazione...
start "" FP-DMS.exe
exit";

            File.WriteAllText(launcherFile, launcherContent);
        }

        private void CreateDesktopShortcut()
        {
            try
            {
                string shortcutPath = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.Desktop), "FP Digital Marketing Suite.lnk");
                string targetPath = Path.Combine(installPath, "FP-DMS.exe");
                
                // Create shortcut using WScript
                string shortcutScript = $@"
Set oWS = WScript.CreateObject(""WScript.Shell"")
sLinkFile = ""{shortcutPath}""
Set oLink = oWS.CreateShortcut(sLinkFile)
oLink.TargetPath = ""{targetPath}""
oLink.WorkingDirectory = ""{installPath}""
oLink.Description = ""FP Digital Marketing Suite""
oLink.Save
";
                
                string scriptFile = Path.Combine(Path.GetTempPath(), "createshortcut.vbs");
                File.WriteAllText(scriptFile, shortcutScript);
                
                Process.Start("wscript.exe", scriptFile).WaitForExit();
                File.Delete(scriptFile);
            }
            catch
            {
                // Ignore shortcut creation errors
            }
        }

        private void UpdateStatus(string status, int progress)
        {
            statusLabel.Text = status;
            progressBar.Value = progress;
            Application.DoEvents();
        }
    }

    public static class Program
    {
        [STAThread]
        public static void Main()
        {
            Application.EnableVisualStyles();
            Application.SetCompatibleTextRenderingDefault(false);
            Application.Run(new InstallerForm());
        }
    }
}
