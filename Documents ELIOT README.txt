~/Documents/ELIOT/
├── README.md               # Deskripsi project, cara setup, dan dokumentasi singkat
├── .gitignore              # Untuk ignore file seperti logs, cache, dll.
├── config/                 # Folder untuk konfigurasi global
│   ├── env.php             # Environment vars untuk web (DB conn, API keys)
│   ├── mqtt_bridge.ini     # Config untuk bridge Python (MQTT broker, HTTP endpoints)
│   └── device_config.h     # Config header untuk microcontroller (jika C-based)
├── web/                    # Folder utama web app (symlink ke htdocs)
│   ├── index.php           # Entry point web (redirect ke public atau admin)
│   ├── public/             # Bagian user-facing (misal halaman login, search buku)
│   │   ├── assets/         # CSS, JS, images untuk public
│   │   │   ├── css/
│   │   │   ├── js/
│   │   │   └── img/
│   │   ├── controllers/    # MVC: Controller untuk public (misal search, view item)
│   │   ├── models/         # MVC: Model untuk DB interaction (buku, user)
│   │   ├── views/          # MVC: Views/template untuk public
│   │   └── api/            # Endpoint API untuk integrasi (misal HTTP dari bridge)
│   │       └── borrow.php  # Contoh API untuk pinjam barang
│   ├── apps/               # Khusus admin (manajemen perpustakaan)
│   │   ├── dashboard.php   # Halaman utama admin
│   │   ├── assets/         # CSS, JS khusus admin
│   │   │   ├── css/
│   │   │   ├── js/
│   │   │   └── img/
│   │   ├── controllers/    # Controller admin (add/edit buku, user mgmt)
│   │   ├── models/         # Model admin-specific
│   │   ├── views/          # Views untuk admin panels
│   │   └── reports/        # Generate laporan (misal stok, pinjam)
│   └── includes/           # Shared files untuk web
│       ├── db.php          # Koneksi database MySQL
│       ├── auth.php        # Auth system (login, session)
│       └── helpers.php     # Fungsi helper umum
├── bridge/                 # Python untuk bridge MQTT <-> HTTP
│   ├── requirements.txt    # Dependencies (misal paho-mqtt, requests)
│   ├── main.py             # Script utama: Subscribe MQTT, post ke HTTP API
│   ├── mqtt_handler.py     # Handle MQTT messages dari device
│   ├── http_client.py      # Send data ke web API
│   ├── logger.py           # Logging untuk debug
│   └── tests/              # Folder untuk unit tests Python
│       └── test_bridge.py
├── device/                 # Kode untuk microcontroller/alat
│   ├── firmware/           # Kode utama (misal Arduino .ino atau ESP32)
│   │   ├── eliot_device.ino # Contoh kode: Sensor, MQTT publish
│   │   └── libraries/      # Lib custom jika perlu
│   ├── schematics/         # Diagram hardware (misal Fritzing files)
│   │   └── eliot_circuit.fzz
│   └── docs/               # Dokumentasi hardware (pinout, wiring)
│       └── hardware.md
├── docs/                   # Dokumentasi project keseluruhan
│   ├── api.md              # Dokumentasi API web
│   ├── database.sql        # Schema DB (tabel buku, user, transaksi)
│   └── setup_guide.md      # Cara install dan run project
└── logs/                   # Folder untuk logs (gitignore content-nya)
    ├── web.log
    └── bridge.log
    
    628141
