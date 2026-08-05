<footer class="border-t border-[#d8d0c3] px-6 py-[30px] font-sans text-[.9rem] text-[#66615a]">
    <div>
        <p>&copy; {{ date('Y') }} {{ $site->long_name ?? $site->name }}. Todos los derechos reservados.</p>
        <nav>
            <a href="{{ $subdirUrl }}/archive/index.html">📜 Archivo Histórico Histórico</a>
        </nav>
    </div>
</footer>
