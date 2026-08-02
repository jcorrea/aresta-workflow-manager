<script setup>
const year = new Date().getFullYear();
</script>

<template>
    <!-- Rodapé do starter kit (docs/brand/components/footer.html): reage ao tema claro/escuro
         (ao contrário do login), copyright em mono à esquerda, links à direita. -->
    <footer class="flex items-center justify-between border-t border-ink/10 px-6 py-4">
        <p class="font-mono text-xs text-ink/60">{{ year }} © aresta</p>
        <div class="flex gap-1">
            <!-- Como no kit, os links vêm sem rota real — trocar quando "Sobre"/"Suporte" existirem. -->
            <a href="#" class="px-1 py-2 text-sm font-semibold text-ink/60 hover:text-ink">Sobre</a>
            <a href="#" class="px-1 py-2 text-sm font-semibold text-ink/60 hover:text-ink">Suporte</a>
        </div>
    </footer>
</template>
