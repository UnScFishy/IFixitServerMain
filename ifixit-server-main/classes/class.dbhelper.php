<?php
// require_once __DIR__ . '/../vendor/autoload.php';
// require_once('../config.php');

// use Kreait\Firebase\Factory;

// class FirebaseDB {
//     private static $self;
//     private $firestore;

//     public function __construct(array $config = []) {
//         $config = array_merge([
//             'service_account_file' => FIREBASE_SERVICE_ACCOUNT,
//         ], $config);

//         if (!file_exists($config['service_account_file'])) {
//             throw new Exception("Service account file not found: " . $config['service_account_file']);
//         }

//         $factory = (new Factory)
//             ->withServiceAccount($config['service_account_file']);

//         $this->firestore = $factory->createFirestore()->database();
//         static::$self = $this;
//     }

//     public function getFirestore() {
//         return $this->firestore;
//     }

//     public function createDocument($collection, $data) {
//         try {
//             $docRef = $this->firestore->collection($collection)->add($data);
//             return $docRef->id();
//         } catch (Exception $e) {
//             throw new Exception("Failed to create document: " . $e->getMessage());
//         }
//     }

//     public function getDocument($collection, $documentId) {
//         $docRef = $this->firestore->collection($collection)->document($documentId);
//         $snapshot = $docRef->snapshot();
    
//         if ($snapshot->exists()) {
//             $data = $snapshot->data();
//             $data['id'] = $snapshot->id(); 
//             return $data;
//         }
//         return ['error' => 'Document not found.'];
//     }

//     public function getAllDocuments($collection) {
//         $documents = $this->firestore->collection($collection)->documents();
//         $results = [];

//         foreach ($documents as $doc) {
//             if ($doc->exists()) {
//                 $data = $doc->data();
//                 $data['id'] = $doc->id();
//                 $results[] = $data;
//             }
//         }

//         return $results;
//     }    

//     public function updateDocument($collection, $documentId, $data) {
//         try {
//             $docRef = $this->firestore->collection($collection)->document($documentId);
//             $docRef->set($data, ['merge' => true]);
//             return true;
//         } catch (Exception $e) {
//             throw new Exception("Failed to update document: " . $e->getMessage());
//         }
//     }

//     public function deleteDocument($collection, $documentId) {
//         try {
//             $docRef = $this->firestore->collection($collection)->document($documentId);
//             $docRef->delete();
//             return true;
//         } catch (Exception $e) {
//             throw new Exception("Failed to delete document: " . $e->getMessage());
//         }
//     }

//     public function queryCollection($collection, $conditions = []) {
//         try {
//             $query = $this->firestore->collection($collection);
//             foreach ($conditions as $condition) {
//                 $query = $query->where($condition['field'], $condition['operator'], $condition['value']);
//             }
    
//             $documents = $query->documents();
//             $results = [];
//             foreach ($documents as $doc) {
//                 if ($doc->exists()) {
//                     $data = $doc->data();
//                     $data['id'] = $doc->id(); 
//                     $results[] = $data;
//                 }
//             }
    
//             return $results;
//         } catch (Exception $e) {
//             throw new Exception("Failed to query collection: " . $e->getMessage());
//         }
//     }
    
// }
