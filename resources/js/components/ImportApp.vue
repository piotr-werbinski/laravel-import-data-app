<template>
  <div class="max-w-3xl mx-auto p-6 font-sans">
    <h2 class="text-2xl font-bold text-gray-800 mb-6">Import Transakcji Bankowych</h2>

    <div class="mb-8">
      <input 
        type="file" 
        @change="uploadFile" 
        class="block w-full text-sm text-gray-500
               file:mr-4 file:py-2 file:px-4
               file:rounded-lg file:border-0
               file:text-sm file:font-semibold
               file:bg-blue-50 file:text-blue-700
               hover:file:bg-blue-100
               cursor-pointer"
      />
    </div>

    <h3 class="text-xl font-semibold text-gray-700 mb-4">Lista importów</h3>

    <div class="overflow-x-auto">
      <table class="min-w-full border border-gray-200 rounded-lg overflow-hidden">
        <thead class="bg-gray-100">
          <tr>
            <th class="px-4 py-2 text-left text-gray-600">ID</th>
            <th class="px-4 py-2 text-left text-gray-600">Plik</th>
            <th class="px-4 py-2 text-left text-gray-600">Rekordy</th>
            <th class="px-4 py-2 text-left text-gray-600">Status</th>
            <th class="px-4 py-2 text-left text-gray-600">Logi</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="imp in imports" :key="imp.id" class="border-t border-gray-200 hover:bg-gray-50">
            <td class="px-4 py-2">{{ imp.id }}</td>
            <td class="px-4 py-2">{{ imp.file_name }}</td>
            
            <td class="px-4 py-2">
              <div class="w-full bg-gray-200 rounded-full h-4">
                <div
                  class="h-4 rounded-full bg-green-500 transition-all duration-300"
                  :style="{ width: (imp.successful_records / imp.total_records * 100) + '%' }"
                ></div>
              </div>
              <div class="text-sm text-gray-600 mt-1">
                {{ imp.successful_records }}/{{ imp.total_records }}
              </div>
            </td>

            <td class="px-4 py-2">
              <span 
                :class="{
                  'text-green-700 font-semibold': imp.status === 'success',
                  'text-yellow-600 font-semibold': imp.status === 'partial',
                  'text-red-600 font-semibold': imp.status === 'failed'
                }"
              >
                {{ imp.status }}
              </span>
            </td>

            <td class="px-4 py-2">
              <button 
                :disabled="imp.status === 'success'"
                @click="showLogs(imp)"
                :class="[
                  imp.status === 'success' 
                    ? 'px-3 py-1 bg-gray-300 text-gray-500 rounded cursor-not-allowed'
                    : 'px-3 py-1 bg-blue-500 text-white rounded hover:bg-blue-600 transition-colors cursor-pointer'
                ]"
              >
                Pokaż logi
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Logi -->
    <div v-if="logs.length" ref="logsSection" class="mt-8">
      <h3 class="text-xl font-semibold text-gray-700 mb-4">Logi dla importu #{{ selectedImport }}</h3>

      <div class="overflow-x-auto">
        <table class="min-w-full border border-gray-200 rounded-lg overflow-hidden">
          <thead class="bg-gray-100">
            <tr>
              <th class="px-4 py-2 text-left text-gray-600">ID</th>
              <th class="px-4 py-2 text-left text-gray-600">transaction_id</th>
              <th class="px-4 py-2 text-left text-gray-600">Błąd</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="log in logs" :key="log.id" class="border-t border-gray-200 hover:bg-gray-50">
              <td class="px-4 py-2">{{ log.id }}</td>
              <td class="px-4 py-2">{{ log.transaction_id }}</td>
              <td class="px-4 py-2 text-red-600">{{ log.error_message }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script>
import axios from "axios";

export default {
  data() {
    return {
      imports: [],
      logs: [],
      selectedImport: null
    };
  },

  mounted() {
    this.loadImports();
  },

  methods: {
    async uploadFile(event) {
      const file = event.target.files[0];
      const form = new FormData();
      form.append("file", file);

      await axios.post("/api/imports", form);
      this.loadImports();
    },

    async loadImports() {
      const res = await axios.get("/api/imports");
      this.imports = res.data;
    },

    async loadLogs(id) {
      const res = await axios.get("/api/imports/" + id);
      this.selectedImport = id;
      this.logs = res.data.logs;
    },

    async showLogs(imp) {
      if (imp.status === 'success') return;

      await this.loadLogs(imp.id);

      this.$nextTick(() => {
        const el = this.$refs.logsSection;
        if (el) {
          el.scrollIntoView({ behavior: 'smooth' });
        }
      });
    }
  }
};
</script>
