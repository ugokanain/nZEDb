<?php

require_once dirname(__FILE__).'/archivereader.php';
require_once dirname(__FILE__).'/rarinfo.php';
require_once dirname(__FILE__).'/zipinfo.php';
require_once dirname(__FILE__).'/srrinfo.php';
require_once dirname(__FILE__).'/par2info.php';
require_once dirname(__FILE__).'/sfvinfo.php';

/**
 * ArchiveInfo class.
 *
 * This is an example implementation of a class that provides a facade for all of
 * the archive readers in the library. It will automatically detect any valid file
 * or data type, and parse its contents via delegation to the correct reader. The
 * API should then be identical to the reader's API for any given archive type.
 *
 * It also supports recursively inspecting the contents of any archives packed within
 * other archives (only if uncompressed) via extra methods that either allow chained
 * calls to the embedded archive objects or return a flat list of contents with the
 * source path info included as an extra field.
 *
 * Note that since the class exposes the interfaces of different readers directly,
 * any application using it should add extra checks for expected properties, methods
 * and returned values, depending on the source type and reader interface. In other
 * words, apply duck typing.
 *
 * Example usage:
 *
 * <code>
 *
 *   // Load the archive file or data
 *   $archive = new ArchiveInfo;
 *   $archive->open('./foo.rar'); // or $archive->setData($data);
 *   if ($archive->error) {
 *     echo "Error: {$archive->error}\n";
 *     exit;
 *   }
 *
 *   // Check the archive type
 *   if ($archive->type != ArchiveInfo::TYPE_RAR) {
 *     echo "Source is not a RAR archive\n";
 *     // exit here or continue with duck typing
 *   }
 *
 *   // Check encryption
 *   if (!empty($archive->isEncrypted)) {
 *     echo "Archive is password encrypted\n";
 *     exit;
 *   }
 *
 *   // List the contents of all archives recursively
 *   foreach ($archive->getArchiveFileList() as $file) {
 *     if (isset($file['error'])) {
 *       echo "Error: {$file['error']} (in: {$file['source']})\n";
 *       continue; // skip recursion errors
 *     }
 *     if (!empty($file['pass'])) {
 *       echo "File is passworded: {$file['name']} (in: {$file['source']})\n";
 *       continue; // skip encrypted files
 *     }
 *     if (empty($file['compressed'])) {
 *       echo "Extracting uncompressed file: {$file['name']} from: {$file['source']}\n";
 *       $archive->saveFileData($file['name'], "./dir/{$file['name']}", $file['source']);
 *       // or $data = $archive->getFileData($file['name'], $file['source']);
 *     }
 *   }
 *
 * </code>
 *
 * @author     Hecks
 * @copyright  (c) 2010-2013 Hecks
 * @license    Modified BSD
 * @version    1.6
 */
class ArchiveInfo extends ArchiveReader
{
	/**#@+
	 * Supported archive types.
	 */
	const TYPE_NONE    = 0x0000;
	const TYPE_RAR     = 0x0002;
	const TYPE_ZIP     = 0x0004;
	const TYPE_SRR     = 0x0008;
	const TYPE_SFV     = 0x0010;
	const TYPE_PAR2    = 0x0020;

	/**#@-*/

	/**
	 * Source path label of the main archive.
	 */
	const MAIN_SOURCE  = 'main';

	/**
	 * The default list of the supported archive reader classes.
	 * @var array
	 */
	protected $readers = array(
		self::TYPE_RAR  => 'RarInfo',
		self::TYPE_SRR  => 'SrrInfo',
		self::TYPE_PAR2 => 'Par2Info',
		self::TYPE_ZIP  => 'ZipInfo',
		self::TYPE_SFV  => 'SfvInfo',
	);

	/**
	 * The current archive file/data type.
	 * @var integer
	 */
	public $type = self::TYPE_NONE;

	/**
	 * Sets the list of supported archive reader classes for the current instance,
	 * overriding the defaults. The keys should be valid archive types:
	 *
	 *    $archive->setReaders(array(ArchiveInfo::TYPE_RAR => 'RarInfo'));
	 *
	 * If $recursive is set to true, this list will also be used for all embedded
	 * archives as well. This can be a bit unpredictable, so use with caution, but
	 * it's a convenient way to swap in custom readers or change their order.
	 *
	 * @param   array    $readers    list of reader classes keyed by archive type
	 * @param   boolean  $recursive  apply list to all embedded archives?
	 * @return  void
	 */
	public function setReaders(array $readers, $recursive=false)
	{
		$this->readers = $readers;
		$this->inheritReaders = $recursive;
	}

	/**
	 * Convenience method that outputs a summary list of the archive information,
	 * useful for pretty-printing.
	 *
	 * When called with $full set to true, this method will also return a nested
	 * summary of all the embedded archive contents in the 'archives' field, keyed
	 * to the archive filenames.
	 *
	 * @param   boolean  $full  return a full summary?
	 * @return  array    archive summary
	 */
	public function getSummary($full=false)
	{
		$summary = array(
			'main_info' => isset($this->readers[$this->type]) ? $this->readers[$this->type] : 'Unknown',
			'main_type' => $this->type,
			'file_name' => $this->file,
			'file_size' => $this->fileSize,
			'data_size' => $this->dataSize,
			'use_range' => "{$this->start}-{$this->end}",
		);
		if ($this->error) {
			$summary['error'] = $this->error;
		}
		if ($this->reader) {
			$args = func_get_args();
			$summary += $this->__call('getSummary', $args);
		}
		if ($full && $this->containsArchive()) {
			$summary['archives'] = $this->getArchiveList(true); // recursive
		}

		return $summary;
	}

	/**
	 * Returns a list of records for each of the files in the archive by delegation
	 * to the stored reader.
	 *
	 * @return  array|boolean  list of file records, or false if none are available
	 */
	public function getFileList()
	{
		if ($this->reader) {
			$args = func_get_args();
			return $this->__call('getFileList', $args);
		}

		return false;
	}

	/**
	 * Returns a list of the parsed data found in the source in human-readable
	 * format (for debugging purposes only).
	 *
	 * @return  array|boolean  parsed data, or false if none available
	 */
	public function getParsedData()
	{
		switch ($this->type) {
			case self::TYPE_RAR:
			case self::TYPE_SRR:
				return $this->reader->getBlocks();
			case self::TYPE_PAR2:
				return $this->reader->getPackets();
			case self::TYPE_ZIP:
				return $this->reader->getRecords();
			case self::TYPE_SFV:
				return $this->reader->getFileList();
			default:
				return false;
		}
	}

	/**
	 * Returns the stored archive reader instance for the file/data type.
	 *
	 * @return  ArchiveReader
	 */
	public function getReader()
	{
		return $this->reader;
	}

	/**
	 * Determines whether the current archive type can contain other archives
	 * through which it can search recursively.
	 *
	 * @return  boolean
	 */
	public function allowsRecursion()
	{
		return ($this->type == self::TYPE_RAR || $this->type == self::TYPE_ZIP);
	}

	/**
	 * Determines whether the current archive contains another archive.
	 *
	 * @return  boolean
	 */
	public function containsArchive()
	{
		$this->getArchiveList();
		return !empty($this->archives);
	}

	/**
	 * Lists any embedded archives, either as raw ArchiveInfo objects or as file
	 * summaries, and caches the object list locally.
	 *
	 * @param   boolean  $summary  return file summaries?
	 * @return  array|boolean  list of stored objects/summaries, or false on error
	 */
	public function getArchiveList($summary=false)
	{
		if (!$this->reader || !$this->allowsRecursion())
			return false;

		if (empty($this->archives)) foreach ($this->reader->getFileList() as $file) {
			$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
			if (preg_match('/(rar|r[0-9]+|zip|srr|par2|sfv)/', $ext)) {
				if ($archive = $this->getArchive($file['name'])) {
					$this->archives[$file['name']] = $archive;
				}
			}
		}
		if ($summary) {
			$ret = array();
			foreach ($this->archives as $name => $archive) {
				$ret[$name] = $archive->getSummary(true); // recursive
			}
			return $ret;
		}

		return $this->archives;
	}

	/**
	 * Returns an ArchiveInfo object for an embedded archive file with the contents
	 * analyzed (initially without recursion). Calls to this method can also be
	 * chained together to navigate the tree, e.g.:
	 *
	 *    $rar->getArchive('parent.rar')->getArchive('child.zip')->getFileList();
	 *
	 * @param   string   $filename   the embedded archive filename
	 * @return  ArchiveInfo|boolean  false if an object can't be returned
	 */
	public function getArchive($filename)
	{
		if (!$this->reader || !$this->allowsRecursion())
			return false;

		// Check the cache first
		if (isset($this->archives[$filename]))
			return $this->archives[$filename];

		foreach ($this->reader->getFileList(true) as $file) {
			if ($file['name'] == $filename && isset($file['range'])) {

				// Create the new archive object
				$archive = new self;
				if ($this->inheritReaders) {
					$archive->setReaders($this->readers, true);
				}

				// We shouldn't process any files that are unreadable
				if (!empty($file['compressed']) || !empty($file['pass'])) {
					$archive->readers = array();
				}

				// Try to parse the source file/data
				$range = explode('-', $file['range']);
				if ($this->file) {
					$archive->open($this->file, $this->isFragment, $range);
				} else {
					$archive->setData($this->data, $this->isFragment, $range);
				}

				// Make error messages more specific
				if (!empty($file['compressed'])) {
					$archive->error = 'The archive is compressed and cannot be read';
				}
				if (!empty($file['pass']) || !empty($archive->isEncrypted)) {
					$archive->error = 'The archive is encrypted and cannot be read';
				}

				return $archive;
			}
		}

		// Something went wrong
		return false;
	}

	/**
	 * Provides the contents of the current archive in a flat list, optionally
	 * recursing through all embedded archives as well, with a 'source' field
	 * added to each item that includes the archive source path.
	 *
	 * If $all is set to true, the file lists of all the supported archive types
	 * will be merged in the flat list, not just those that allow recursion. This
	 * should be used with caution, as the output varies between readers and the
	 * only guaranteed results are:
	 *
	 *     array('name'  => '...', 'source' => '...') or:
	 *     array('error' => '...', 'source' => '...')
	 *
	 * It's really just handy for inspecting all known file names in the laziest
	 * way possible, and not much more than that.
	 *
	 * @param   boolean  $recurse   list all archive contents recursively?
	 * @param   string   $all       include all supported archive file lists?
	 * @param   string   $source    [ignore, for internal use only]
	 * @return  array|boolean  the flat archive file list, or false on error
	 */
	public function getArchiveFileList($recurse=true, $all=false, $source=null)
	{
		if (!$this->reader) {return false;}
		$ret = array();

		// Start with the main parent
		if ($source == null) {
			$source = self::MAIN_SOURCE;
			if ($ret = $this->reader->getFileList()) {
				$ret = $this->flattenFileList($ret, $source, $all);
			}
		}

		// Merge each archive file list
		if ($recurse && $this->containsArchive()) {
			foreach ($this->getArchiveList() as $name => $archive) {

				// Only include the file lists of types that allow recursion?
				if (empty($archive->error) && !$all && !$archive->allowsRecursion())
					continue;
				$branch = $source.' > '.$name;

				// We should append any errors
				if ($archive->error || !($files = $archive->getFileList())) {
					$error = $archive->error ? $archive->error : 'No files found';
					$ret[] = array('error' => $error, 'source' => $branch);
					continue;
				}

				// Otherwise merge recursively
				$ret = array_merge($ret, $this->flattenFileList($files, $branch, $all));
				if ($archive->containsArchive()) {
					$ret = array_merge($ret, $archive->getArchiveFileList(true, $all, $branch));
				}
			}
		}

		return $ret;
	}

	/**
	 * Extracts the data for the given filename and optionally the archive source
	 * (e.g. 'main' or 'main > child.rar', etc.).
	 *
	 * @param   string  $filename  name of the file to extract
	 * @param   string  $source    archive source path of the file
	 * @return  string|boolean  file data, or false on error
	 */
	public function getFileData($filename, $source=self::MAIN_SOURCE)
	{
		// Check that a valid data source is available
		if (!$this->reader || ($this->reader->data == '' && $this->reader->handle == null))
			return false;

		// Get the absolute start/end positions
		if (!($range = $this->getFileRangeInfo($filename, $source))) {
			$in_source = $source ? " in: ({$source})" : '';
			$this->error = "Could not find file info for: ({$filename}){$in_source}";
			return false;
		}
		$this->error = '';

		return $this->reader->getRange($range);
	}

	/**
	 * Saves the data for the given filename and optionally archive source path
	 * to the given destination (e.g. 'main' or 'main > child.rar', etc.).
	 *
	 * @param   string  $filename     name of the file to extract
	 * @param   string  $destination  full path of the file to create
	 * @param   string  $source       archive source path of the file
	 * @return  integer|boolean  number of bytes saved or false on error
	 */
	public function saveFileData($filename, $destination, $source=self::MAIN_SOURCE)
	{
		// Check that a valid data source is available
		if (!$this->reader || ($this->reader->data == '' && $this->reader->handle == null))
			return false;

		// Get the absolute start/end positions
		if (!($range = $this->getFileRangeInfo($filename, $source))) {
			$in_source = $source ? " in: ({$source})" : '';
			$this->error = "Could not find file info for: ({$filename}){$in_source}";
			return false;
		}
		$this->error = '';

		return $this->reader->saveRange($range, $destination);
	}

	/**
	 * Returns the position of the first archive marker/signature in the stored
	 * data or file by delegation to the stored reader.
	 *
	 * @return  mixed  Marker position, or false if marker is missing
	 */
	public function findMarker()
	{
		if ($this->reader)
			return $this->reader->findMarker();

		return false;
	}

	/**
	 * Magic method for accessing the properties of the stored reader.
	 *
	 * @param   string  $name  the property name
	 * @return  mixed   the propery value
	 */
	public function __get($name)
	{
		if ($this->reader && isset($this->reader->$name))
			return $this->reader->$name;

		return parent::__get($name);
	}

	/**
	 * Magic method for delegating method calls to the stored reader.
	 *
	 * @param   string  $method  the method name
	 * @param   array   $args    the method arguments
	 * @return  mixed   result of the delegated method call
	 * @throws  BadMethodCallException
	 */
	public function __call($method, $args)
	{
		if (!$this->reader)
			throw new BadMethodCallException(get_class($this)."::$method() is not defined");

		switch (count($args)) {
			case 0:
				return $this->reader->$method();
			case 1:
				return $this->reader->$method($args[0]);
			case 2:
				return $this->reader->$method($args[0], $args[1]);
			case 3:
				return $this->reader->$method($args[0], $args[1], $args[2]);
			default:
				return call_user_func_array(array($this->reader, $method), $args);
		}
	}

	/**
	 * The stored archive reader instance.
	 * @var ArchiveReader
	 */
	protected $reader;

	/**
	 * Cached list of any embedded archive objects.
	 * @var array
	 */
	protected $archives = array();

	/**
	 * Should any embedded archives inherit the readers list from this instance?
	 * @var boolean
	 */
	protected $inheritReaders = false;

	/**
	 * Parses the source file/data by delegation to one of the configured readers,
	 * or returns false if the source is not a supported type.
	 *
	 * @return  boolean  false if parsing fails
	 */
	protected function analyze()
	{
		// Create a reader to handle the file/data
		if ($this->createReader() === false || $this->findMarker() === false) {
			$this->error = 'Source is not a supported archive type';
			$this->close();
			return false;
		}

		// Delegate some properties to the reader
		unset($this->markerPosition);
		unset($this->fileCount);
		unset($this->error);

		// Let the reader handle any files
		if ($this->file) {
			$this->close();
		}

		return true;
	}

	/**
	 * Creates a reader instance for parsing the source file/data and stores it
	 * locally for any later delegation.
	 *
	 * Each reader in the configured order tries to parse the source file/data and
	 * find a valid marker/signature for its type. Where more than one marker is
	 * present - such as with embedded archives - the reader that finds the earliest
	 * marker will be used as the delegate.
	 *
	 * @return  boolean  false if no reader could parse the source
	 */
	protected function createReader()
	{
		$range = array($this->start, $this->end);

		foreach ($this->readers as $type => $class) {

			// Analyze the source with a new reader
			$reader = new $class;
			if ($this->file) {
				$reader->open($this->file, $this->isFragment, $range);
			} else {
				$reader->setData($this->data, $this->isFragment, $range);
			}
			if ($reader->error) {continue;}

			// Store the reader with the earliest marker
			if (($marker = $reader->findMarker()) !== false) {
				$start = !isset($start) ? $marker : $start;
				if ($marker <= $start) {
					$start = $marker;
					$this->reader = $reader;
					$this->type = $type;
				}
				if ($start === 0) {break;}
			}
		}

		return isset($this->reader);
	}

	/**
	 * Returns the absolute start and end positions for the given filename and
	 * optionally archive source in the current file/data.
	 *
	 * @param   string  $filename  the filename to search
	 * @param   string  $source    archive source path of the file
	 * @return  array|boolean  the range info or false on error
	 */
	protected function getFileRangeInfo($filename, $source=self::MAIN_SOURCE)
	{
		if (strpos($source, self::MAIN_SOURCE) !== 0) {
			$source = self::MAIN_SOURCE.' > '.$source;
		}
		foreach ($this->getArchiveFileList(true) as $file) {
			if (!empty($file['name']) && empty($file['is_dir']) && !empty($file['range'])
			 && $file['name'] == $filename && $file['source'] == $source
			) {
				return explode('-', $file['range']);
			}
		}

		return false;
	}

	/**
	 * Helper method that flattens a file list that may have children, removes keys
	 * and re-indexes, then adds a source path field to each item.
	 *
	 * @param   array    $files   the file list to flatten
	 * @param   string   $source  the current source path info
	 * @param   boolean  $all     should any child lists be included?
	 * @return  array  the flat file list
	 */
	protected function flattenFileList(array $files, $source, $all=false)
	{
		$files = array_values($files);
		$children = array();
		foreach ($files as &$file) {
			$file['source'] = $source;
			if ($source != self::MAIN_SOURCE) {
				unset($file['next_offset']);
			}
			if ($all && !empty($file['files'])) foreach ($file['files'] as $child) {
				$child['source'] = $source.' > '.$file['name'];
				unset($child['next_offset']);
				$children[] = $child;
			}
		}
		if (!empty($children)) {
			$files = array_merge($files, $children);
		}

		return $files;
	}

	/**
	 * Resets the instance variables before parsing new data.
	 *
	 * @return  void
	 */
	protected function reset()
	{
		parent::reset();
		$this->type = self::TYPE_NONE;
		$this->reader = null;
		$this->archives = array();
	}

} // End ArchiveInfo class
