<?php

namespace App\Http\Controllers\CRUD;

use App\Http\Controllers\Controller;

use App\Services\StockTransactionService;
use App\Services\ProductService;
use App\Services\UserService;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;


class StockTransactionController extends Controller
{
    protected $stockTransactionService;
    protected $productService;
    protected $userService;

    public function __construct(
        StockTransactionService $stockTransactionService,
        ProductService $productService,
        UserService $userService
    ) {
        $this->stockTransactionService = $stockTransactionService;
        $this->productService = $productService;
        $this->userService = $userService;
    }

    public function create()
    {
        if (Auth::user()->role !== 'Manajer Gudang') {
            return redirect()->route('manager.stock_transactions.index')->with('error', 'Unauthorized.');
        }

        $products = $this->productService->getAllProducts();
        $users = $this->userService->getAllUsers();
        return view('manager.stock_transactions.create', compact('products', 'users'));
    }

    /**
     * Menyimpan transaksi stok baru (Hanya Manager, status selalu 'pending')
     */
    public function store(Request $request)
    {
        if (Auth::user()->role !== 'Manajer Gudang') {
            return redirect()->route('manager.stock_transactions.index')->with('error', 'Unauthorized.');
        }

        $request->validate([
            'product_id' => 'required|exists:products,id',
            'type' => 'required|in:masuk,keluar',
            'quantity' => 'required|integer|min:1',
            'date' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        $this->stockTransactionService->createStockTransaction(Auth::id(), $request->all());

        return redirect()->route('manager.stock_transactions.index')->with('success', 'Transaksi stok berhasil dibuat.');
    }

    /**
     * Menampilkan transaksi yang masih pending (Hanya Staff)
     */
    public function pending()
    {
        if (Auth::user()->role !== 'Staff Gudang') {
            return redirect()->route('stock_transactions.index')->with('error', 'Unauthorized.');
        }

        $transactions = $this->stockTransactionService->getPendingTransactions();
        return view('stock_transactions.pending', compact('transactions'));
    }

    /**
     * Konfirmasi transaksi stok (Staff dapat mengubah status menjadi 'received' atau 'dispatched')
     */
    public function confirm(Request $request, $id)
    {
        if (Auth::user()->role !== 'Staff Gudang') {
            return redirect()->route('stock_transactions.index')->with('error', 'Unauthorized.');
        }

        $request->validate([
            'status' => 'required|in:received,dispatched',
        ]);

        try {
            $this->stockTransactionService->confirmTransaction($id, $request->status);
            return response()->json(['message' => 'Transaksi berhasil dikonfirmasi.']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Menampilkan semua transaksi stok (Hanya Admin)
     */
    public function indexAdmin()
    {
        if (Auth::user()->role !== 'Admin') {
            return redirect()->route('admin.home')->with('error', 'Unauthorized.');
        }

        $transactions = $this->stockTransactionService->getAllTransactionsSortedByDate();
        return view('admin.stock_transactions.index', compact('transactions'));
    }

    public function staffindex()
    {
        $transactions = $this->stockTransactionService->getAllTransactionsSortedByDate();
        return view('staff.stock_transactions.index', compact('transactions'));
    }

    public function managerindex()
    {
        $transactions = $this->stockTransactionService->getAllTransactionsSortedByDate();
        return view('manager.stock_transactions.index', compact('transactions'));
    }

    /**
     * Display the specified resource.
     */
    public function managerShow($transactionId)
    {
        // Pastikan objek memiliki relasi yang diperlukan
        $stockTransaction = $this->stockTransactionService->getTransactionWithRelations($transactionId);


        return view('manager.stock_transactions.show', compact('stockTransaction'));
    }


        /**
     * Menampilkan form edit transaksi stok.
     * Hanya Manager yang bisa mengedit transaksi yang dibuatnya dan masih berstatus "pending".
     */
    public function manageredit($transactionId)
    {
        $stockTransaction = $this->stockTransactionService->getTransactionById($transactionId);
        $products = $this->productService->getAllProducts();
        $users = $this->userService->getAllUsers();
        return view('manager.stock_transactions.edit', compact('stockTransaction', 'products', 'users'));
    }

    public function managerupdate(Request $request, $transactionId)
    {
        $user = Auth::user();
        $stockTransaction = $this->stockTransactionService->getTransactionById($transactionId);

        // Cek apakah user adalah manager dan hanya bisa update transaksi miliknya yang masih pending
        if ($user->role !== 'Manajer Gudang' || $stockTransaction->user_id !== $user->id || $stockTransaction->status !== 'pending') {
            return redirect()->route('manager.stock_transactions.index')->with('error', 'Unauthorized.');
        }

        // Validasi data
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'type' => 'required|in:masuk,keluar',
            'quantity' => 'required|integer|min:1',
            'date' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        // Update data transaksi stok
        $stockTransaction->update([
            'product_id' => $request->product_id,
            'type' => $request->type,
            'quantity' => $request->quantity,
            'date' => $request->date,
            'notes' => $request->notes,
        ]);

        return redirect()->route('manager.stock_transactions.index')->with('success', 'Stock transaction updated successfully.');
    }


}


