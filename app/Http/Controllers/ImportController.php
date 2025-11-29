<?php

namespace App\Http\Controllers;

use App\Models\Import;
use App\Models\Transaction;
use App\Models\ImportLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ImportController extends Controller
{
    public function index()
    {
        return Import::orderBy('created_at', 'desc')->get();
    }

    public function show($id)
    {
        return Import::with('logs')->findOrFail($id);
    }

    public function store(Request $request)
    {
        $request->validate([
            'file' => 'required|file'
        ]);

        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());

        // ===== Tworzenie importu =====
        $import = Import::create([
            'file_name' => $file->getClientOriginalName(),
            'total_records' => 0,
            'successful_records' => 0,
            'failed_records' => 0,
            'status' => 'failed',
        ]);

        // ===== Próba parsowania pliku =====
        try {
            $records = $this->parseFile($file, $extension);
        } catch (\Exception $e) {

            ImportLog::create([
                'import_id' => $import->id,
                'transaction_id' => null,
                'error_message' => 'Parse error: ' . $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Invalid file',
                'error'   => $e->getMessage()
            ], 422);
        }

        // ===== Liczniki =====
        $total = count($records);
        $success = 0;
        $failed = 0;

        // ===== Przetwarzanie rekordów =====
        foreach ($records as $row) {

            // Normalizacja kluczy i wartości
            $row = $this->normalizeKeys($row);

            // Normalizacja wartości
            $row['account_number'] = isset($row['account_number'])
                ? strtoupper(str_replace(' ', '', $row['account_number']))
                : null;

            $row['currency'] = isset($row['currency'])
                ? strtoupper(trim($row['currency']))
                : null;

            if (isset($row['amount'])) {
                $clean = str_replace([' ', ','], ['', '.'], (string)$row['amount']);
                $row['amount'] = is_numeric($clean) ? (float)$clean : $row['amount'];
            }

            // Walidacja
            $validator = Validator::make($row, [
                'transaction_id'   => 'required|uuid',
                'account_number'   => ['required', 'regex:/^[A-Z]{2}[0-9A-Z]{13,30}$/'],
                'transaction_date' => 'required|date',
                'amount'           => 'required|numeric|min:0.01',
                'currency'         => 'required|size:3',
            ]);

            if ($validator->fails()) {

                ImportLog::create([
                    'import_id'      => $import->id,
                    'transaction_id' => $row['transaction_id'] ?? null,
                    'error_message'  => implode(', ', $validator->errors()->all()),
                ]);

                $failed++;
                continue;
            }

            // Zapis poprawnego rekordu
            Transaction::create($row);
            $success++;
        }

        // ===== Aktualizacja importu =====
        $import->update([
            'total_records'       => $total,
            'successful_records'  => $success,
            'failed_records'      => $failed,
            'status'              => $failed === 0 ? 'success' : ($success > 0 ? 'partial' : 'failed'),
        ]);

        return response()->json([
            'message' => 'Import completed',
            'import'  => $import
        ]);
    }



    // ===========================================================
    // Normalizacja kluczy
    // ===========================================================
    private function normalizeKeys(array $row): array
    {
        $new = [];
        foreach ($row as $k => $v) {
            $nk = strtolower(trim($k));
            $nk = str_replace([' ', '-', '.'], '_', $nk);
            $new[$nk] = $v;
        }
        return $new;
    }



    // ===========================================================
    // PARSOWANIE PLIKÓw
    // ===========================================================
    private function parseFile($file, $extension)
    {
        $contents = file_get_contents($file);

        return match ($extension) {
            'csv'  => $this->parseCSV($contents),
            'json' => $this->parseJSON($contents),
            'xml'  => $this->parseXML($contents),
            default => throw new \Exception("Unsupported file type: $extension"),
        };
    }



    // ===========================================================
    // CSV parser (z normalizacją nagłówków)
    // ===========================================================
    private function parseCSV($contents)
    {
        $rows = [];

        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents);

        $fp = fopen('php://temp', 'r+');
        fwrite($fp, $contents);
        rewind($fp);

        $headers = fgetcsv($fp);
        $headers = array_filter($headers, fn($h) => $h !== null && $h !== '');

        // Normalizacja nagłówków
        $headers = array_map(function ($h) {
            $h = trim($h);
            $h = strtolower($h);
            return str_replace([' ', '-', '.'], '_', $h);
        }, $headers);

        while (($data = fgetcsv($fp)) !== false) {

            if ($data === null || $data === [null] || count(array_filter($data)) === 0) {
                continue;
            }

            if (count($data) > count($headers)) {
                $data = array_slice($data, 0, count($headers));
            }

            if (count($data) < count($headers)) {
                $data = array_pad($data, count($headers), null);
            }

            $rows[] = array_combine($headers, $data);
        }

        return $rows;
    }



    // ===========================================================
    // JSON parser
    // ===========================================================
    private function parseJSON($contents)
    {
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents);

        $data = json_decode($contents, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Invalid JSON: ' . json_last_error_msg());
        }

        if (!isset($data[0])) {
            $data = [$data];
        }

        $allKeys = [];
        foreach ($data as $item) {
            if (is_array($item)) {
                $allKeys = array_unique(array_merge($allKeys, array_keys($item)));
            }
        }

        foreach ($data as &$row) {
            foreach ($allKeys as $key) {
                if (!array_key_exists($key, $row)) {
                    $row[$key] = null;
                }
            }
            $row = array_intersect_key($row, array_flip($allKeys));
        }

        return $data;
    }



    // ===========================================================
    // XML parser
    // ===========================================================
    private function parseXML($contents)
    {
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents);

        $contents = preg_replace('/[^\x09\x0A\x0D\x20-\x7F]/', '', $contents);

        $contents = preg_replace('/&(?![A-Za-z0-9#]+;)/', '&amp;', $contents);

        libxml_use_internal_errors(true);

        $xml = simplexml_load_string($contents, "SimpleXMLElement", LIBXML_NOCDATA);
        if (!$xml) {
            $errors = libxml_get_errors();
            libxml_clear_errors();

            $msg = "Invalid XML. Errors: ";
            foreach ($errors as $e) {
                $msg .= trim($e->message) . " | ";
            }
            throw new \Exception($msg);
        }

        $json = json_decode(json_encode($xml), true);

        $rows = $json['transaction'] ?? [];

        if (!isset($rows[0])) {
            $rows = [$rows];
        }

        $allKeys = [];
        foreach ($rows as $row) {
            $allKeys = array_unique(array_merge($allKeys, array_keys($row)));
        }

        foreach ($rows as &$row) {
            foreach ($allKeys as $key) {
                if (!array_key_exists($key, $row)) {
                    $row[$key] = null;
                }
            }
            $row = array_intersect_key($row, array_flip($allKeys));
        }

        return $rows;
    }
}
