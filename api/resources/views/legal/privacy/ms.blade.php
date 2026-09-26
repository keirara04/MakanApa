{{-- Notis privasi dalam Bahasa Malaysia. Keep section numbers and ids in step with legal/privacy/en.blade.php. --}}
<h1 class="font-display text-[clamp(3.2rem,9vw,5rem)] font-bold uppercase leading-[0.88] tracking-tight">Notis Privasi</h1>
<p class="mt-2 text-sm text-ink/70">Kemas kini terakhir: {{ $updated }}</p>
<p class="mt-4 text-ink/70">
    Notis ini menerangkan data peribadi yang dikumpul oleh MakanApa, sebab ia dikumpul, pihak yang menerimanya, tempoh
    ia disimpan dan pilihan yang ada pada anda, seperti yang dikehendaki oleh Akta Perlindungan Data Peribadi 2010
    (APDP). MakanApa dikendalikan oleh {{ config('legal.operator_name') }} di Malaysia ("kami"), yang bertanggungjawab
    ke atas data anda.
</p>
<p class="mt-2 text-sm text-ink/70">
    Notis ini juga disediakan dalam bahasa Inggeris. Jika terdapat perbezaan antara kedua-dua versi, versi bahasa
    Inggeris akan terpakai.
</p>
@include('legal.partials.links')

<ul class="sketch mt-6 divide-y divide-dashed divide-ink/20 bg-paper-50 text-sm" style="--sketch-radius: 12px">
    <li class="p-4"><strong class="text-ink">Tiada iklan, tiada penjualan data.</strong> Kami tidak memaparkan iklan, tidak menjual data anda dan tidak menjejaki anda merentasi aplikasi atau laman web syarikat lain.</li>
    <li class="p-4"><strong class="text-ink">Lokasi disimpan bersama pilihan anda.</strong> Kami menyimpan lokasi anda ketika anda meminta cadangan, dipautkan kepada akaun anda. Lokasi ini tidak sekali-kali dipaparkan kepada umum.</li>
    <li class="p-4"><strong class="text-ink">Akaun tidak diwajibkan.</strong> Anda boleh menggunakan MakanApa sebagai tetamu tanpa memberikan nama atau e-mel.</li>
    <li class="p-4"><strong class="text-ink">Anda yang mengawal.</strong> Anda boleh mengemas kini profil, menetapkan semula profil selera dan memadam akaun anda dalam aplikasi pada bila-bila masa.</li>
</ul>

<div class="mt-10 space-y-10 text-ink/80 leading-relaxed">

    <section id="collect">
        <h2 class="{{ $h2 }}">01. Data yang kami kumpul</h2>
        <p class="mt-4 font-medium text-ink">Maklumat akaun</p>
        <p class="mt-2">
            Apabila anda membuat akaun, kami mengumpul nama, alamat e-mel dan avatar yang anda pilih daripada set kami.
            Jika anda log masuk dengan Apple atau Google, kami menyimpan pengecam akaun yang diberikan oleh mereka supaya
            kami dapat mengenali anda, dan bagi Apple, satu kebenaran log masuk (disimpan secara tersulit) supaya kami
            dapat membatalkannya jika anda memadam akaun. Jika anda menetapkan kata laluan, kami hanya menyimpan cincangan
            (hash) selamatnya. Kami juga menyimpan tetapan anda, seperti pilihan halal dan pilihan pemberitahuan.
        </p>
        <p class="mt-2">
            Akaun tetamu tidak mempunyai maklumat ini: hanya pengecam akaun rawak yang dikaitkan dengan aplikasi pada
            peranti anda.
        </p>

        <p id="location" class="mt-4 font-medium text-ink">Lokasi</p>
        <p class="mt-2">
            Jika anda membenarkan akses lokasi, aplikasi menghantar lokasi peranti anda bersama permintaan untuk mencari
            tempat berdekatan, memilih tempat, membuat carian dan memaparkan tempat popular dalam komuniti anda. Kami
            menyimpan lokasi tepat anda bagi setiap cadangan yang anda minta, dipautkan kepada akaun anda. Kami
            menggunakannya untuk memberikan cadangan, dan secara agregat untuk memahami kawasan yang dicari oleh
            pengguna. Lokasi anda tidak sekali-kali dipaparkan kepada pengguna lain.
        </p>

        <p class="mt-4 font-medium text-ink">Pilihan dan aktiviti anda</p>
        <p class="mt-2">
            Apabila anda menggunakan MakanApa, kami merekodkan apa yang anda minta dan apa yang berlaku: mood, bajet dan
            jarak anda, cadangan yang kami tunjukkan, tempat yang anda pilih, cara anda berinteraksi dengan sesuatu
            cadangan (contohnya memilih semula atau melaraskannya), tempat yang anda simpan, undian suasana (vibe),
            perkataan yang anda cari apabila anda memilih tempat daripada carian, dan cara anda membuka sesuatu tempat
            (contohnya daripada pautan yang dikongsi atau pemberitahuan). Kami juga mencatat konteks sesuatu cadangan,
            seperti waktu dan sama ada hujan di kawasan anda.
        </p>

        <p class="mt-4 font-medium text-ink">Profil selera ("Selera")</p>
        <p class="mt-2">
            Berdasarkan aktiviti anda, MakanApa membina profil selera supaya cadangan lebih sesuai dengan anda. Anda boleh
            melihat apa yang telah dipelajari, membetulkannya dan menetapkannya semula dalam aplikasi.
        </p>

        <p class="mt-4 font-medium text-ink">Komuniti dan sumbangan</p>
        <p class="mt-2">Jika anda menggunakan ciri komuniti, kami mengumpul apa yang anda kongsi dan lakukan di sana:</p>
        <ul class="mt-2 list-disc pl-5 space-y-1 text-sm">
            <li>komuniti anda (universiti atau kawasan) dan status pengesahannya;</li>
            <li>hantaran dan balasan komuniti (dengan tag tempat pilihan), serta reaksi anda;</li>
            <li>laporan yang anda buat (sebab dan nota pilihan), serta orang yang anda sekat;</li>
            <li>tempat yang anda tambah atau sunting, dan foto yang anda muat naik;</li>
            <li>pengesahan halal (vouch): dakwaan, ulasan, butiran sijil dan foto anda;</li>
            <li>tuntutan pemilikan dan bukti yang anda lampirkan;</li>
            <li>permintaan untuk menambah universiti atau kawasan yang belum disenaraikan;</li>
            <li>versi Terma Penggunaan dan Garis Panduan Komuniti yang anda persetujui, serta masanya.</li>
        </ul>

        <p class="mt-4 font-medium text-ink">Pemberitahuan</p>
        <p class="mt-2">
            Jika anda membenarkan pemberitahuan, kami menyimpan token tolak (push token) peranti anda, pengecam rawak bagi
            pemasangan aplikasi anda, dan sama ada ia binaan ujian atau binaan sebenar. Kami menyimpan rekod
            pemberitahuan yang kami hantar kepada anda, dan jika anda mengaktifkan cadangan waktu makan, masa cadangan
            seterusnya.
        </p>

        <p class="mt-4 font-medium text-ink">Penggunaan aplikasi</p>
        <p class="mt-2">
            Kami merekodkan masa anda membuka dan menutup aplikasi, bersama pengecam rawak pemasangan anda. Pengecam ini
            dijana oleh MakanApa, bukan pengecam pengiklanan peranti anda. Kami menggunakan rekod ini hanya untuk
            statistik dalaman kami, seperti bilangan pengguna setiap minggu. Kami tidak menggunakan alat analitik atau
            pelaporan ranap (crash) pihak ketiga.
        </p>

        <p class="mt-4 font-medium text-ink">Carian tanpa hasil</p>
        <p class="mt-2">
            Apabila sesuatu carian tidak menemui apa-apa, kami menyimpan perkataan carian dan kawasan anggaran (kira-kira
            1 km), tanpa sebarang pautan kepada anda, supaya kami tahu tempat yang belum ada dalam MakanApa.
        </p>

        <p class="mt-4 font-medium text-ink">Sokongan dan laman web ini</p>
        <p class="mt-2">
            Jika anda menghantar e-mel kepada kami, kami menyimpan e-mel tersebut. Laman web ini menyimpan kiraan tanpa
            nama bagi paparan halaman dan klik butang muat turun. Ia tidak menetapkan kuki atau menyimpan alamat IP atau
            pengecam peranti untuk tujuan ini.
        </p>
    </section>

    <section id="sources">
        <h2 class="{{ $h2 }}">02. Sumber data</h2>
        <ul class="mt-2 list-disc pl-5 space-y-1 text-sm">
            <li>Daripada anda, apabila anda mendaftar, menggunakan aplikasi atau menghubungi kami.</li>
            <li>Daripada peranti anda, seperti lokasi dan token tolak, apabila anda membenarkannya.</li>
            <li>Daripada Apple atau Google, jika anda log masuk dengan mereka (nama, e-mel dan pengecam akaun anda).</li>
            <li>Daripada cara anda menggunakan MakanApa, seperti pilihan dan profil selera anda.</li>
            <li>Daripada pasukan kami, seperti status pengesahan komuniti anda.</li>
        </ul>
    </section>

    <section id="purposes">
        <h2 class="{{ $h2 }}">03. Tujuan kami menggunakannya</h2>
        <ul class="mt-2 list-disc pl-5 space-y-1 text-sm">
            <li>Untuk mengendalikan akaun anda dan membolehkan anda log masuk.</li>
            <li>Untuk mencari tempat berdekatan, mencadangkan satu tempat dan mengingati tempat yang anda simpan.</li>
            <li>Untuk memperibadikan cadangan melalui profil selera anda.</li>
            <li>Untuk mengendalikan komuniti: memaparkan hantaran, mengurus laporan dan sekatan, menapis kandungan dan menguatkuasakan Terma Penggunaan kami.</li>
            <li>Untuk menyemak maklumat restoran dan halal sebelum ia dipaparkan.</li>
            <li>Untuk menghantar pemberitahuan yang anda pilih.</li>
            <li>Untuk memahami cara MakanApa digunakan dan menambah baiknya, menggunakan statistik dalaman kami sahaja.</li>
            <li>Untuk memastikan MakanApa selamat dan mencegah penyalahgunaan, seperti spam dan pengelakan had penggunaan.</li>
            <li>Untuk mematuhi undang-undang dan menjawab permintaan yang sah.</li>
        </ul>
    </section>

    <section id="visible">
        <h2 class="{{ $h2 }}">04. Apa yang boleh dilihat oleh orang lain</h2>
        <ul class="mt-2 list-disc pl-5 space-y-1 text-sm">
            <li>Hantaran dan balasan komuniti anda, bersama nama paparan dan avatar anda, boleh dilihat oleh orang dalam komuniti yang sama.</li>
            <li>Selepas diluluskan, pengesahan halal anda dipaparkan pada tempat berkenaan bersama nama paparan, ulasan dan foto anda.</li>
            <li>Selepas diluluskan, tempat, butiran dan foto yang anda sumbangkan menjadi sebahagian daripada maklumat restoran MakanApa.</li>
            <li>Laporan adalah tanpa nama: penulis tidak diberitahu siapa yang melaporkannya. Orang yang anda sekat tidak diberitahu.</li>
            <li>Halaman tempat yang dikongsi di laman web ini memaparkan maklumat restoran dan bilangan orang yang memilih tempat itu, tetapi tidak sekali-kali siapa mereka.</li>
            <li>Lokasi, e-mel dan aktiviti anda tidak sekali-kali dipaparkan kepada pengguna lain.</li>
        </ul>
    </section>

    <section id="sharing">
        <h2 class="{{ $h2 }}">05. Pihak yang menerima data</h2>
        <p class="mt-2">Kami hanya berkongsi apa yang diperlukan oleh setiap perkhidmatan untuk menjalankan tugasnya bagi pihak kami:</p>
        <ul class="sketch mt-4 divide-y divide-dashed divide-ink/20 bg-paper-50 text-sm" style="--sketch-radius: 12px">
            <li class="p-4"><strong class="text-ink">Apple</strong>: Sign in with Apple, dan penghantaran pemberitahuan tolak (token tolak anda dan pemberitahuan tersebut).</li>
            <li class="p-4"><strong class="text-ink">Google</strong>: Google Sign-In; Google Places, yang dihubungi oleh pelayan kami untuk mendapatkan maklumat restoran menggunakan perkataan carian anda dan lokasi di kawasan anda (yang boleh jadi lokasi anda); dan peta Google dalam aplikasi, yang disediakan terus oleh Google di bawah <a href="https://policies.google.com/privacy" class="{{ $link }}" rel="noopener">Dasar Privasi Google</a>.</li>
            <li class="p-4"><strong class="text-ink">OpenRouter</strong> dan penyedia model AI yang digunakannya: teks idaman makanan yang anda taip (sehingga 200 aksara), supaya kami dapat memahaminya; dan butiran pengesahan halal (dakwaan, ulasan dan butiran sijil, tidak sekali-kali nama atau akaun anda), supaya pasukan kami mendapat cadangan semakan. Cadangan AI tidak sekali-kali mengubah status halal atau membuang kandungan dengan sendirinya.</li>
            <li class="p-4"><strong class="text-ink">Open-Meteo</strong>: kawasan anggaran kira-kira 5 km, dihantar dari pelayan kami, untuk menyemak sama ada hujan. Tidak sekali-kali lokasi tepat anda.</li>
            <li class="p-4"><strong class="text-ink">Penyedia pengehosan dan storan</strong>: mengendalikan pelayan kami dan menyimpan foto yang dimuat naik.</li>
            <li class="p-4"><strong class="text-ink">Penyedia e-mel</strong>: mengendalikan e-mel yang anda hantar ke alamat sokongan kami.</li>
            <li class="p-4"><strong class="text-ink">Pihak berkuasa</strong>: apabila dikehendaki oleh undang-undang, atau untuk melindungi keselamatan orang ramai.</li>
        </ul>
        <p class="mt-3">
            Sesetengah penyedia ini memproses data di luar Malaysia, contohnya di Amerika Syarikat atau Eropah. Kami hanya
            menghantar apa yang mereka perlukan, dan mereka mengendalikannya di bawah komitmen privasi dan keselamatan
            mereka sendiri.
        </p>
    </section>

    <section id="photos">
        <h2 class="{{ $h2 }}">06. Foto</h2>
        <p class="mt-2">
            Foto yang anda muat naik dikodkan semula di pelayan kami, yang membuang metadata tersembunyi seperti koordinat
            GPS dan butiran peranti. Foto kekal peribadi sehingga diluluskan oleh pasukan kami, dan kemudian disimpan
            dalam storan objek yang serasi dengan S3. Foto restoran daripada Google Places dihantar terus ke peranti anda
            dan tidak disimpan oleh kami.
        </p>
    </section>

    <section id="notifications">
        <h2 class="{{ $h2 }}">07. Pemberitahuan</h2>
        <p class="mt-2">Anda memilih pemberitahuan yang ingin diterima dalam Tetapan aplikasi:</p>
        <ul class="mt-2 list-disc pl-5 space-y-1 text-sm">
            <li>Kemas kini tentang tempat dan pengesahan halal yang anda hantar: aktif secara lalai</li>
            <li>Notis akaun dan pentadbir: aktif secara lalai</li>
            <li>Balasan kepada hantaran anda: aktif secara lalai</li>
            <li>Reaksi kepada hantaran anda: tidak aktif secara lalai</li>
            <li>Cadangan waktu makan, maksimum sekali sehari: tidak aktif secara lalai</li>
            <li>Berita dan pengumuman keluaran baharu: tidak aktif secara lalai</li>
        </ul>
        <p class="mt-2">Anda juga boleh mematikan semua pemberitahuan dalam Tetapan iPhone anda.</p>
    </section>

    <section id="retention">
        <h2 class="{{ $h2 }}">08. Tempoh penyimpanan</h2>
        <ul class="mt-2 list-disc pl-5 space-y-1 text-sm">
            <li>Maklumat akaun: sehingga anda memadam akaun.</li>
            <li>Pilihan dan aktiviti: disimpan untuk menambah baik MakanApa. Apabila anda memadam akaun, ia disimpan tanpa sebarang pautan kepada akaun tersebut.</li>
            <li>Akaun tetamu: dipadam secara automatik selepas 90 hari tanpa aktiviti.</li>
            <li>Sesi log masuk: tamat tempoh selepas 90 hari, dan sesi yang tamat tempoh dibuang setiap hari.</li>
            <li>Teks yang dihantar untuk semakan AI: dibuang daripada rekod kami selepas 90 hari.</li>
            <li>Penghantaran yang tidak lengkap: draf dibuang selepas 7 hari. Foto daripada penghantaran yang dibatalkan dibuang, dan foto daripada penghantaran yang ditolak dibuang selepas 30 hari.</li>
            <li>Carian tanpa hasil: disimpan tanpa nama, tanpa pautan kepada sesiapa.</li>
        </ul>
    </section>

    <section id="rights">
        <h2 class="{{ $h2 }}">09. Pilihan dan hak anda</h2>
        <ul class="mt-2 list-disc pl-5 space-y-1 text-sm">
            <li><strong class="text-ink">Melihat dan membetulkan data anda:</strong> kemas kini nama, avatar dan komuniti anda dalam aplikasi, serta lihat atau tetapkan semula profil selera anda. Untuk meminta salinan data anda atau membetulkan perkara lain, hantar e-mel kepada kami. Kami akan menjawab dalam tempoh 21 hari.</li>
            <li><strong class="text-ink">Mengehadkan data yang dikumpul:</strong> matikan lokasi atau pemberitahuan dalam Tetapan iPhone anda, pilih jenis pemberitahuan dalam aplikasi, tetapkan semula profil selera anda, atau gunakan MakanApa sebagai tetamu.</li>
            <li><strong class="text-ink">Menarik balik persetujuan:</strong> padam akaun anda dalam aplikasi pada bila-bila masa (lihat di bawah).</li>
            <li><strong class="text-ink">Membuat aduan:</strong> hubungi kami terlebih dahulu. Anda juga boleh menghubungi Pesuruhjaya Perlindungan Data Peribadi Malaysia di <a href="https://www.pdp.gov.my/" class="{{ $link }}" rel="noopener">pdp.gov.my</a>.</li>
        </ul>
        <p class="mt-4 font-medium text-ink">Data wajib dan pilihan</p>
        <ul class="mt-2 list-disc pl-5 space-y-1 text-sm">
            <li>Alamat e-mel (atau Sign in with Apple atau Google) diwajibkan untuk membuat akaun, dan nama bagi pendaftaran melalui e-mel. Tanpanya, anda masih boleh menggunakan MakanApa sebagai tetamu.</li>
            <li>Lokasi adalah pilihan, tetapi tanpanya kami tidak dapat mencari tempat berdekatan dengan anda.</li>
            <li>Pemberitahuan, ciri komuniti dan sumbangan adalah pilihan.</li>
        </ul>
    </section>

    <section id="children">
        <h2 class="{{ $h2 }}">10. Umur</h2>
        <p class="mt-2">
            MakanApa adalah untuk mereka yang berumur {{ config('legal.minimum_age') }} tahun ke atas. Jika anda berumur
            bawah 18 tahun, anda memerlukan persetujuan ibu bapa atau penjaga untuk menggunakannya. Jika kami mendapati
            seseorang yang berumur bawah {{ config('legal.minimum_age') }} tahun mempunyai akaun, kami akan memadamnya.
        </p>
    </section>

    <section id="security">
        <h2 class="{{ $h2 }}">11. Keselamatan</h2>
        <p class="mt-2">
            Data dihantar melalui sambungan yang disulitkan. Kata laluan dan token log masuk disimpan sebagai cincangan
            selamat, dan kebenaran log masuk Apple disulitkan. Hanya pasukan pentadbir yang kecil boleh mengakses data
            peribadi. Jika berlaku pelanggaran data yang membahayakan data anda, kami akan memaklumkan anda dan pihak
            berkuasa seperti yang dikehendaki oleh undang-undang.
        </p>
    </section>

    <section id="deletion">
        <h2 class="{{ $h2 }}">12. Memadam akaun anda</h2>
        <p class="mt-2">
            Anda boleh memadam akaun dalam aplikasi (Tetapan → Padam akaun). Pemadaman berlaku serta-merta, kekal dan
            tidak boleh dibatalkan.
        </p>

        <p class="mt-4 font-medium text-ink">Apa yang dipadam:</p>
        <ul class="mt-2 list-disc pl-5 space-y-1 text-sm">
            <li>Nama, e-mel, avatar dan kata laluan anda</li>
            <li>Pengecam akaun Apple atau Google anda (kebenaran log masuk Apple anda dibatalkan)</li>
            <li>Sesi log masuk anda</li>
            <li>Komuniti, tetapan pemberitahuan dan jadual cadangan waktu makan anda</li>
            <li>Profil selera anda dan aktiviti yang digunakan untuk membinanya</li>
            <li>Rekod buka dan tutup aplikasi anda</li>
            <li>Hantaran, balasan dan reaksi komuniti anda, laporan yang anda buat, dan sekatan anda</li>
            <li>Permintaan komuniti anda dan sebarang pemilikan tempat</li>
            <li>Foto yang anda muat naik, termasuk fail dan rekodnya</li>
        </ul>

        <p class="mt-4 font-medium text-ink">Apa yang disimpan, tanpa pautan kepada akaun anda:</p>
        <ul class="mt-2 list-disc pl-5 space-y-1 text-sm">
            <li>Pilihan anda, termasuk lokasinya, dan undian suasana (bersama pengecam pemasangan rawak)</li>
            <li>Tempat yang anda simpan (bersama pengecam pemasangan rawak)</li>
            <li>Tempat, suntingan, pengesahan halal dan tuntutan pemilikan yang anda hantar. Pengesahan yang diluluskan kemudiannya dipaparkan sebagai daripada "MakanApa user"</li>
            <li>Token tolak peranti anda, supaya pemberitahuan berhenti tetapi rekodnya kekal</li>
            <li>Rekod pemberitahuan yang kami hantar kepada anda</li>
        </ul>
        <p class="mt-2 text-sm text-ink/70">
            Kami menyimpan data ini kerana maklumat restoran dan statistik keseluruhan masih berguna kepada orang lain
            selepas sesuatu akaun dipadam.
        </p>

        <p class="mt-4 font-medium text-ink">Apa yang kami kekalkan:</p>
        <p class="mt-2 text-sm text-ink/70">
            Satu rekod yang mengandungi alamat e-mel anda dan tarikh pemadaman, untuk tujuan keselamatan dan untuk menjawab
            pertanyaan tentang akaun yang telah dipadam. Rekod ini tidak disimpan bagi akaun tetamu, yang tidak mempunyai
            e-mel.
        </p>
    </section>

    <section id="changes">
        <h2 class="{{ $h2 }}">13. Perubahan kepada notis ini</h2>
        <p class="mt-2">
            Apabila kami mengubah notis ini, kami akan mengemas kini tarikh di bahagian atas. Jika perubahan itu ketara,
            kami juga akan memaklumkan anda dalam aplikasi.
        </p>
    </section>

    <section id="contact">
        <h2 class="{{ $h2 }}">14. Hubungi kami</h2>
        <p class="mt-2">
            Ada soalan, permintaan atau aduan tentang data anda?
            @if ($privacyEmail)
                Hantar e-mel ke <a href="mailto:{{ $privacyEmail }}" class="{{ $link }}">{{ $privacyEmail }}</a>.
            @else
                Gunakan <a href="{{ url('/support') }}" class="{{ $link }}">halaman sokongan</a>.
            @endif
        </p>
    </section>

</div>
