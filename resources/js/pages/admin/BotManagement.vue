<template>
    <div class="bot-management-page space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-semibold text-foreground">Управление ботом</h1>
                <p v-if="bot" class="text-muted-foreground mt-1">{{ bot.name }}</p>
            </div>
            <button
                @click="$router.push({ name: 'admin.bots' })"
                class="h-11 px-6 bg-accent/10 backdrop-blur-xl text-accent border border-accent/40 hover:bg-accent/20 rounded-2xl shadow-lg shadow-accent/10 inline-flex items-center justify-center gap-2"
            >
                ← Назад к ботам
            </button>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="flex items-center justify-center py-12">
            <p class="text-muted-foreground">Загрузка данных...</p>
        </div>

        <!-- Error State -->
        <div v-if="error" class="p-4 bg-destructive/10 border border-destructive/20 rounded-lg">
            <p class="text-destructive">{{ error }}</p>
        </div>

        <!-- Tabs -->
        <div v-if="!loading && bot" class="bg-card rounded-lg border border-border">
            <div class="border-b border-border">
                <nav class="flex -mb-px">
                    <button
                        v-for="tab in tabs"
                        :key="tab.key"
                        @click="activeTab = tab.key"
                        :class="[
                            'px-6 py-4 text-sm font-medium border-b-2 transition-colors',
                            activeTab === tab.key
                                ? 'border-accent text-accent'
                                : 'border-transparent text-muted-foreground hover:text-foreground hover:border-muted-foreground'
                        ]"
                    >
                        {{ tab.label }}
                    </button>
                </nav>
            </div>

            <div class="p-6">
                <!-- Consultations Tab -->
                <div v-if="activeTab === 'consultations'">
                    <ConsultationTable :bot-id="botId" />
                </div>

                <!-- Materials Tab -->
                <div v-if="activeTab === 'materials'">
                    <MaterialCategoryTree :bot-id="botId" />
                </div>

                <!-- Settings Tab -->
                <div v-if="activeTab === 'settings'">
                    <BotSettingsForm :bot-id="botId" @updated="fetchBot" />
                </div>

                <!-- Statistics Tab -->
                <div v-if="activeTab === 'statistics'">
                    <BotStatistics :bot-id="botId" />
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, onMounted, computed } from 'vue'
import { useRoute } from 'vue-router'
import { apiGet } from '../../utils/api'
import ConsultationTable from '../../components/bot/ConsultationTable.vue'
import MaterialCategoryTree from '../../components/bot/MaterialCategoryTree.vue'
import BotSettingsForm from '../../components/bot/BotSettingsForm.vue'
import BotStatistics from '../../components/bot/BotStatistics.vue'

export default {
    name: 'BotManagement',
    components: {
        ConsultationTable,
        MaterialCategoryTree,
        BotSettingsForm,
        BotStatistics,
    },
    setup() {
        const route = useRoute()
        const botId = computed(() => route.params.botId)
        const loading = ref(false)
        const error = ref(null)
        const bot = ref(null)
        const activeTab = ref('consultations')

        const tabs = [
            { key: 'consultations', label: 'Заявки' },
            { key: 'materials', label: 'Материалы' },
            { key: 'settings', label: 'Настройки' },
            { key: 'statistics', label: 'Статистика' },
        ]

        const fetchBot = async () => {
            loading.value = true
            error.value = null
            try {
                const response = await apiGet(`/bots/${botId.value}`)
                if (!response.ok) {
                    const errorData = await response.json().catch(() => ({}))
                    throw new Error(errorData.message || 'Ошибка загрузки бота')
                }
                const data = await response.json()
                bot.value = data.data || null
            } catch (err) {
                error.value = err.message || 'Ошибка загрузки бота'
            } finally {
                loading.value = false
            }
        }

        onMounted(() => {
            fetchBot()
        })

        return {
            botId,
            loading,
            error,
            bot,
            activeTab,
            tabs,
            fetchBot,
        }
    },
}
</script>


