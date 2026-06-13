Product Requirement Document (PRD)
Fitur Klaim Distributor 
________________________________________
2. Latar Belakang
Saat ini program promosi distributor masih dihitung secara manual menggunakan file Excel sehingga memerlukan waktu untuk menentukan strata, diskon per kilogram, dan total diskon yang berhak diterima distributor.
Aplikasi ini bertujuan mengotomatisasi proses perhitungan berdasarkan master program yang telah ditentukan.
________________________________________
3. Tujuan Sistem
1.	Menyimpan master program promosi distributor.
2.	Menyimpan strata program berdasarkan customer type.
3.	Mengunggah data transaksi distributor.
4.	Melakukan validasi transaksi terhadap program aktif.
5.	Menghitung diskon per kilogram secara otomatis.
6.	Menghasilkan laporan diskon program yang dapat diekspor ke Excel.
________________________________________
4. Ruang Lingkup
In Scope
•	Master Program
•	Master Produk Program
•	Master Strata Program
•	Upload Transaksi Distributor
•	Perhitungan Diskon Otomatis
•	Hasil Perhitungan
•	Export Excel
Out of Scope
•	Approval Workflow
•	Payment Process
•	Integrasi SAP
•	Notifikasi Email
•	Mobile Application
________________________________________
5. Master Data
5.1 Master Program
Digunakan untuk menyimpan informasi program.
Field:
•	Program Code
•	Program Name
•	Start Date
•	End Date
•	Description
•	Status
Contoh:
Program Garam Kapal & Garam Jempol Juni 2026
Periode:
01 Juni 2026 – 30 Juni 2026
________________________________________
5.2 Master Produk Program

Digunakan untuk menentukan item yang mengikuti program.

Data item berasal dari master item (tabel items).

User hanya memilih item yang akan mengikuti program.

Item program tidak boleh tumpang tindih.

Contoh:
Program A berlaku 1–15 Juni
Program B berlaku 16–30 Juni
Item X hanya bisa di salah satu program, tidak boleh di keduanya.
________________________________________
5.3 Master Strata Program
Digunakan untuk menentukan harga program dan diskon berdasarkan quantity.
Field:
•	Customer Type
•	Minimum Qty
•	Maximum Qty
•	Harga Program per Kg
•	Diskon per Kg
Contoh:
General Trade
Min Qty	Max Qty	Harga Program	Diskon
3	199	7.700	200
200	499	7.700	250
500	999	7.420	200
1000	UP	7.420	250
________________________________________
6. Upload Transaksi
User mengunggah file transaksi distributor.
Format file:
Kode Customer	Nama Customer	Kode Item	Nama Item	Harga Jual@Kg	Qty@Kg	Type Customer	Transaction Date
Contoh:
Kode Customer	Nama Customer	Kode Item	Qty
C110000411	DUA JAYA, CV	A26	100
C310000510	SIMPANG TIGA, TK	B26.B	100
Format file:
•	XLSX
•	XLS
________________________________________
7. Proses Bisnis
Overview
Sistem digunakan untuk menghitung diskon program distributor berdasarkan data transaksi yang diunggah oleh user dan master program yang telah dibuat sebelumnya.
Sistem akan secara otomatis:
•	Mencari program yang aktif.
•	Memvalidasi produk yang mengikuti program.
•	Menentukan strata berdasarkan quantity.
•	Menghitung diskon per kilogram.
•	Menghasilkan total diskon yang berhak diterima distributor.
________________________________________
Step 1 - Upload File Transaksi
User mengunggah file transaksi distributor dalam format Excel.
Format file:
Kolom
Kode Customer
Nama Customer
Kode Item
Nama Item
Harga Jual @ Kg
Qty @ Kg
Type Customer
Transaction Date
Contoh:
Kode Customer	Nama Customer	Kode Item	Nama Item	Harga Jual	Qty	Type Customer	Transaction Date
C110000411 | DUA JAYA, CV | A26 | TOP 250 M @ 10 KG / BAL | 6400 | 100 | GT | 12-Jun-2026

C310000510 | SIMPANG TIGA, TK | B26.B | KOP 250 M @ 10 KG / BAL | 7700 | 100 | MT | 12-Jun-2026
________________________________________
Step 2 - Validasi Struktur File
Sistem melakukan validasi terhadap file yang diunggah.
Validasi meliputi:
Validasi Header
Sistem memastikan seluruh kolom wajib tersedia:
•	Kode Customer
•	Nama Customer
•	Kode Item
•	Nama Item
•	Harga Jual @ Kg
•	Qty @ Kg
•	Type Customer
•	Transaction Date
Validasi Data
Sistem memastikan:
•	Kode Customer tidak kosong
•	Kode Item tidak kosong
•	Qty lebih besar dari 0
•	Transaction Date valid
•	Type Customer tidak kosong
Jika ditemukan data tidak valid maka sistem menampilkan daftar error dan baris yang bermasalah.
________________________________________
Step 3 - Mencari Program Aktif
Sistem mencari program yang sesuai berdasarkan Transaction Date.
Kriteria:
Sistem mencari program berdasarkan:

1. Transaction Date berada dalam periode program
2. Item termasuk item yang mengikuti program
3. Program berstatus ACTIVE

Transaction Date harus berada di antara Start Date dan End Date Program.
Contoh:
Program:
Program Garam Kapal & Garam Jempol Juni 2026
Periode:
01 Juni 2026 – 30 Juni 2026
Transaction Date:
12 Juni 2026
Hasil:
Program ditemukan dan dapat digunakan untuk proses perhitungan.
________________________________________
Step 4 - Validasi Produk Program
Sistem memeriksa apakah Item Code termasuk dalam daftar produk yang mengikuti program.
Contoh:
Item Code	Status
A26	Valid
B26.B	Valid
X001	Tidak Valid
Jika item tidak termasuk program maka sistem memberikan status:
"Tidak Masuk Program"
dan tidak dilakukan perhitungan diskon.
________________________________________
Step 5 - Validasi Customer Type

Customer Type harus bernilai:

GT
MT

Jika selain itu maka data dianggap invalid.
________________________________________
Step 6 - Penentuan Strata
Sistem menentukan strata berdasarkan:
•	Customer Type
•	Qty Kg
Contoh:
Customer Type:
GT
Qty:
100 Kg
Strata yang ditemukan:
Min Qty	Max Qty	Harga Program	Diskon
3	199	7.700	200
Hasil:
•	Harga Program = Rp 7.700/Kg
•	Diskon = Rp 200/Kg
________________________________________
Step 7 - Perhitungan Diskon
Sistem menghitung total diskon menggunakan rumus:
Total Diskon = Qty Kg × Diskon per Kg
Contoh:
Qty:
100 Kg
Diskon:
Rp 200/Kg
Perhitungan:
100 × 200 = Rp 20.000
________________________________________
Step 8 - Generate Hasil Perhitungan
Sistem menghasilkan data hasil perhitungan program.
Kolom hasil:
•	Kode Customer
•	Nama Customer
•	Kode Item
•	Nama Item
•	Qty Kg
•	Harga Jual per Kg
•	Harga Program per Kg
•	Diskon per Kg
•	Total Diskon
•	Transaction Date
•	Status
Contoh:
Customer	Item	Qty	Harga Jual	Harga Program	Diskon/Kg	Total Diskon	Status
C110000411	A26	100	6.400	7.700	200	20.000	Valid Program
________________________________________
Step 9 - Export Excel
User dapat mengunduh hasil perhitungan ke dalam format Excel.
Kolom export:
•	Kode Customer
•	Nama Customer
•	Kode Item
•	Nama Item
•	Qty Kg
•	Harga Jual per Kg
•	Harga Program per Kg
•	Diskon per Kg
•	Total Diskon
•	Transaction Date
•	Status
________________________________________
Business Rules
1.	Program harus dalam status ACTIVE.
2.	Transaction Date harus berada dalam periode program.
3.	Item harus terdaftar pada produk program.
4.	Qty harus lebih besar dari 0.
5.	Customer Type harus memiliki strata yang sesuai.
6.	Diskon dihitung berdasarkan strata yang ditemukan.
7.	Jika item tidak masuk program maka diskon = 0.
8.	Jika strata tidak ditemukan maka diskon = 0.
9.	Sistem dapat memproses lebih dari satu program aktif selama item program berbeda.
10.	Hasil perhitungan dapat diekspor ke Excel kapan saja.
11. Customer Type yang diperbolehkan hanya GT dan MT.
12. Item program dipilih dari master item yang sudah tersedia pada sistem.
13. Data transaksi upload akan disimpan sebagai histori upload dan dapat ditelusuri kembali berdasarkan batch upload.
________________________________________
8. Output Hasil
Sistem menampilkan hasil perhitungan.
Customer Code	Customer Name	Item Code	Item Name	Qty Kg	Harga Program/Kg	Diskon/Kg	Total Diskon
Contoh:
Customer	Item	Qty	Harga Program	Diskon/Kg	Total Diskon
C110000411	A26	100	7.700	200	20.000
________________________________________
9. Export Excel
User dapat mengekspor hasil perhitungan ke Excel.
Kolom export:
•	Customer Code
•	Customer Name
•	Item Code
•	Item Name
•	Transaction Date
•	Qty Kg
•	Harga Program per Kg
•	Diskon per Kg
•	Total Diskon
________________________________________
10. Struktur Database

Master
• mst_program
• mst_program_item
• mst_program_strata

Transaction
• trx_program_upload_batch
• trx_program_upload

Result
• trx_program_result
________________________________________
11. Hak Akses
Admin Sales
•	Create Program
•	Update Program
•	Upload Transaksi
•	Generate Hasil
•	Export Excel
Viewer
•	View Program
•	View Hasil Perhitungan
•	Export Excel
________________________________________
12. Success Criteria
1.	Sistem dapat membaca file transaksi dengan benar.
2.	Sistem dapat menentukan strata secara otomatis.
3.	Sistem dapat menghitung diskon per kilogram dengan akurat.
4.	Sistem dapat menghasilkan total diskon secara otomatis.
5.	Sistem dapat menghasilkan laporan Excel dalam waktu kurang dari 1 menit untuk 10.000 baris data.

Asumsi Bisnis
•	Perhitungan strata dilakukan berdasarkan Qty per baris transaksi. 
•	Sistem tidak memerlukan proses approval. 
•	Sistem tidak melakukan proses pembayaran klaim. 
•	Sistem hanya menghitung diskon program dan menghasilkan laporan hasil perhitungan. 
•	Satu item hanya boleh aktif pada satu program dalam periode yang sama untuk menghindari konflik perhitungan. Ini penting dicantumkan agar tidak terjadi ambiguity saat implementasi.
DESIGN DATABASE

1. Master program
CREATE TABLE mst_program (
    id BIGSERIAL PRIMARY KEY,

    program_code VARCHAR(30) NOT NULL UNIQUE,
    program_name VARCHAR(200) NOT NULL,

    start_date DATE NOT NULL,
    end_date DATE NOT NULL,

    description TEXT,

    status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE'
        CHECK (status IN ('ACTIVE','INACTIVE')),

    created_by VARCHAR(50),

    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL
);

contoh data

INSERT INTO mst_program (
    program_code,
    program_name,
    start_date,
    end_date,
    description
)
VALUES (
    'PRG202606',
    'Program Garam Kapal & Jempol Juni 2026',
    '2026-06-01',
    '2026-06-30',
    'Program diskon distributor bulan Juni 2026'
);


Master Produk Program
CREATE TABLE mst_program_item (
    id BIGSERIAL PRIMARY KEY,

    program_id BIGINT NOT NULL,
    item_id BIGINT NOT NULL,

    created_at TIMESTAMP DEFAULT NOW(),

    CONSTRAINT fk_program_item_program
        FOREIGN KEY(program_id)
        REFERENCES mst_program(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_program_item_item
        FOREIGN KEY(item_id)
        REFERENCES items(id),

    CONSTRAINT uq_program_item
        UNIQUE(program_id,item_id)
);

contoh data 
INSERT INTO mst_program_item (
    program_id,
    item_id
)
VALUES
(
    1,
    1
),
(
    1,
    2
);

Master Strata Program
CREATE TABLE mst_program_strata (
    id BIGSERIAL PRIMARY KEY,

    program_id BIGINT NOT NULL,

    customer_type VARCHAR(2) NOT NULL
        CHECK (customer_type IN ('GT','MT')),

    min_qty_kg NUMERIC(18,2) NOT NULL,
    max_qty_kg NUMERIC(18,2),

    harga_program_per_kg NUMERIC(18,2) NOT NULL,
    diskon_per_kg NUMERIC(18,2) NOT NULL,

    created_at TIMESTAMP NOT NULL DEFAULT NOW(),

    CONSTRAINT fk_program_strata
        FOREIGN KEY (program_id)
        REFERENCES mst_program(id)
        ON DELETE CASCADE
);

contoh data 
general trade
INSERT INTO mst_program_strata (
    program_id,
    customer_type,
    min_qty_kg,
    max_qty_kg,
    harga_program_per_kg,
    diskon_per_kg
)
VALUES
(1,'GT',3,199,7700,200),
(1,'GT',200,499,7700,250),
(1,'GT',500,999,7420,200),
(1,'GT',1000,NULL,7420,250);

modrn trade
INSERT INTO mst_program_strata (
    program_id,
    customer_type,
    min_qty_kg,
    max_qty_kg,
    harga_program_per_kg,
    diskon_per_kg
)
VALUES
(1,'MT',3,199,8190,200),
(1,'MT',200,499,8190,250),
(1,'MT',500,999,7910,200),
(1,'MT',1000,NULL,7910,250);


upload transaksi 
CREATE TABLE trx_program_upload_batch (
    id BIGSERIAL PRIMARY KEY, 
    batch_no VARCHAR(50) UNIQUE,
    file_name VARCHAR(255),
    uploaded_by VARCHAR(50),
    uploaded_at TIMESTAMP DEFAULT NOW()
);

contoh data 
INSERT INTO trx_program_upload_batch (
    batch_no,
    file_name,
    uploaded_by
)
VALUES (
    'UPLOAD-20260612-001',
    'transaksi_juni.xlsx',
    'admin'
);


tabel trx_program_upload
CREATE TABLE trx_program_upload (
    id BIGSERIAL PRIMARY KEY,

    batch_id BIGINT NOT NULL,

    customer_code VARCHAR(50) NOT NULL,
    customer_name VARCHAR(255),

    item_code VARCHAR(50) NOT NULL,
    item_name VARCHAR(255),

    sell_price_per_kg NUMERIC(18,2),

    qty_kg NUMERIC(18,2) NOT NULL
        CHECK (qty_kg > 0),

    customer_type VARCHAR(2) NOT NULL
        CHECK (customer_type IN ('GT','MT')),

    transaction_date DATE NOT NULL,

    uploaded_at TIMESTAMP DEFAULT NOW(),

    CONSTRAINT fk_upload_batch
        FOREIGN KEY(batch_id)
        REFERENCES trx_program_upload_batch(id)
);

INSERT INTO trx_program_upload (
    batch_id,
    customer_code,
    customer_name,
    item_code,
    item_name,
    sell_price_per_kg,
    qty_kg,
    customer_type,
    transaction_date
)
VALUES
(
    1,
    'C110000411',
    'DUA JAYA, CV',
    'A26',
    'TOP 250 M @ 10 KG / BAL',
    6400,
    100,
    'GT',
    '2026-06-12'
),
(
    1,
    'C310000510',
    'SIMPANG TIGA, TK',
    'B26.B',
    'KOP 250 M @ 10 KG / BAL',
    7700,
    100,
    'MT',
    '2026-06-12'
);



hasil perhitungan
CREATE TABLE trx_program_result (
    id BIGSERIAL PRIMARY KEY,

    upload_id BIGINT NOT NULL,

    program_id BIGINT NOT NULL,

    customer_code VARCHAR(50),
    customer_name VARCHAR(255),

    item_code VARCHAR(50),
    item_name VARCHAR(255),

    qty_kg NUMERIC(18,2),

    sell_price_per_kg NUMERIC(18,2),

    harga_program_per_kg NUMERIC(18,2),

    diskon_per_kg NUMERIC(18,2),

    total_diskon NUMERIC(18,2),

    transaction_date DATE,

    status VARCHAR(30)
        CHECK (
        status IN (
        'VALID_PROGRAM',
        'ITEM_NOT_FOUND',
        'PROGRAM_NOT_FOUND',
        'STRATA_NOT_FOUND'
    )
),

    created_at TIMESTAMP NOT NULL DEFAULT NOW(),

    CONSTRAINT fk_result_upload
        FOREIGN KEY (upload_id)
        REFERENCES trx_program_upload(id),

    CONSTRAINT fk_result_program
        FOREIGN KEY (program_id)
        REFERENCES mst_program(id)
);


Contoh Hasil Perhitungan
Upload:
Customer	Item	Qty	Type
C110000411	A26	100	GT
Cari strata GT:
Min	Max	Harga	Diskon
3	199	7700	200



Perhitungan:

Qty = 100 Kg
Diskon/Kg = 200
Total Diskon = 100 x 200
= 20.000


data hasil

INSERT INTO trx_program_result (
    upload_id,
    program_id,
    customer_code,
    customer_name,
    item_code,
    item_name,
    qty_kg,
    sell_price_per_kg,
    harga_program_per_kg,
    diskon_per_kg,
    total_diskon,
    transaction_date,
    status
)
VALUES (
    1,
    1,
    'C110000411',
    'DUA JAYA, CV',
    'A26',
    'TOP 250 M @ 10 KG / BAL',
    100,
    6400,
    7700,
    200,
    20000,
    '2026-06-12',
    'VALID_PROGRAM'
);


CREATE INDEX idx_program_period
ON mst_program(start_date, end_date);

CREATE INDEX idx_program_item
ON mst_program_item(item_id);

CREATE INDEX idx_strata_lookup
ON mst_program_strata(
    program_id,
    customer_type
);

CREATE INDEX idx_upload_batch
ON trx_program_upload(batch_id);

CREATE INDEX idx_upload_item
ON trx_program_upload(item_code);

CREATE INDEX idx_upload_date
ON trx_program_upload(transaction_date);

CREATE INDEX idx_result_program
ON trx_program_result(program_id);



=======================PERHITUNGAN================================
### ClaimCalculationService Business Logic

Setelah file Excel berhasil di-upload dan data tersimpan ke trx_program_upload, sistem harus melakukan proses kalkulasi dengan urutan berikut:

1. Ambil setiap data transaksi dari trx_program_upload berdasarkan batch_id.

2. Cari item pada tabel items berdasarkan item_code.

   Jika item tidak ditemukan:
   - status = ITEM_NOT_FOUND
   - total_diskon = 0
   - simpan ke trx_program_result

3. Cari program aktif berdasarkan:
   - status = ACTIVE
   - transaction_date berada di antara start_date dan end_date
   - item terdaftar pada mst_program_item

   Jika program tidak ditemukan:
   - status = PROGRAM_NOT_FOUND
   - total_diskon = 0
   - simpan ke trx_program_result

4. Cari strata pada mst_program_strata berdasarkan:
   - program_id
   - customer_type (GT / MT)
   - qty_kg >= min_qty_kg
   - qty_kg <= max_qty_kg

   Untuk strata tertinggi:
   - max_qty_kg dapat bernilai NULL
   - artinya qty >= min_qty_kg

   Jika strata tidak ditemukan:
   - status = STRATA_NOT_FOUND
   - total_diskon = 0
   - simpan ke trx_program_result

5. Jika strata ditemukan:

   harga_program_per_kg = nilai dari strata

   diskon_per_kg = nilai dari strata

   total_diskon =
   qty_kg × diskon_per_kg

6. Simpan ke trx_program_result:

   - upload_id
   - program_id
   - customer_code
   - customer_name
   - item_code
   - item_name
   - qty_kg
   - sell_price_per_kg
   - harga_program_per_kg
   - diskon_per_kg
   - total_diskon
   - transaction_date
   - status = VALID_PROGRAM

7. Generate summary:

   - total_rows
   - valid_rows
   - invalid_rows
   - total_diskon


   ==================contoh==========================
   Contoh:

Upload:

Customer Code : C110000411
Item Code     : A26
Qty           : 100
Customer Type : GT

Program Aktif:
Program Garam Juni 2026

Strata GT:

3 - 199
Harga Program = 7.700
Diskon = 200

Perhitungan:

Total Diskon
=
100 × 200
=
20.000

Hasil:

status = VALID_PROGRAM
harga_program_per_kg = 7700
diskon_per_kg = 200
total_diskon = 20000