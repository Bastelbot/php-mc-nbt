<?php
namespace Bastelbot\McNBT;

class SimpleNamedBinaryTag {

    const DEFLATE_MAX = 65536;  // 1024*64;
    const INFLATE_MAX = 131072; // 1024*128;
    const COMPRESSION_GZIP = 1;
    const COMPRESSION_ZLIB = 2;

    const TAG_End        = 0;
    const TAG_Byte       = 1;
    const TAG_Short      = 2;
    const TAG_Int        = 3;
    const TAG_Long       = 4;
    const TAG_Float      = 5;
    const TAG_Double     = 6;
    const TAG_Byte_Array = 7;
    const TAG_String     = 8;
    const TAG_List       = 9;
    const TAG_Compound   = 10;
    const TAG_Int_Array  = 11;
    const TAG_Long_Array = 12;

    protected $binary = null;
    protected $pos = 0;

    function unserialize ($data)
    {
        $this->binary = $data;
        $this->pos = 0;
        return $this->unserializeTag();
    }

    function serialize ($tag)
    {
        $this->binary = '';
        $this->pos = 0;
        return $this->serializeTag($tag);
    }

    function inflate ($data, $compression, $length = null)
    {
        switch($compression) {
            case self::COMPRESSION_ZLIB:
                //return gzuncompress($data, NamedBinaryTag::INFLATE_MAX);
                return gzuncompress($data, $length);
            case self::COMPRESSION_GZIP:
                return gzdecode($data, $length);
        }
        return false;
    }

    function deflate ($data, $compression, $length = null)
    {
        switch($compression) {
            case self::COMPRESSION_ZLIB:
                //return gzcompress($data, NamedBinaryTag::INFLATE_MAX);
                return gzcompress($data, $length);
            case self::COMPRESSION_GZIP:
                return gzencode($data, $length);
        }
        return false;
    }

    protected function unserializeTag ()
    {
        $tag = array();
        $typ = $this->readByte();
        if(!$typ) return null;
        $tag['t'] = $typ;
        $namelen = $this->readShort();
        $tag['n'] = $this->readString($namelen);
        $tag['d'] = $this->readPayload($typ);
        return $tag;
    }
/*
    protected function dumpTag ($tag)
    {
        if(!$tag) {
            echo "there is no tag\n";
        } else {
            echo "tag: {$tag['id']} {$tag['name']}\n";
        }
    }
*/
    protected function serializeTag ($tag)
    {
//        $this->dumpTag($tag);
        if(empty($tag['t'])) return false;
        $this->writeByte($tag['t']);
        $this->writeString($tag['n']);
        $this->writePayload($tag['t'], $tag['d']);
        return $this->binary;
    }

    protected function readPayload ($id)
    {
        switch($id) {
            case self::TAG_End:
                return 0;
            case self::TAG_Byte:
                return $this->readByte();
            case self::TAG_Short:
                return $this->readShort();
            case self::TAG_Int:
                return $this->readInt();
            case self::TAG_Long:
                return $this->readLong();
            case self::TAG_Float:
                return $this->readFloat();
            case self::TAG_Double:
                return $this->readDouble();
            case self::TAG_Byte_Array:
                $size = $this->readInt();
                $data = array();
                for($i=0; $i<$size; $i++) {
                    $data[] = $this->readByte();
                }
                return $data;
            case self::TAG_String:
                $length = $this->readShort();
                return $this->readString($length);
            case self::TAG_List:
                $list = array();
                $typ = $this->readByte();
                $list['t'] = $typ;
                $size = $this->readInt();
                $list['d'] = array();
                for($i=0; $i<$size; $i++) {
                    $list['d'][] = $this->readPayload($typ);
                }
                return $list;
            case self::TAG_Compound:
                $list = array();
                while ($data = $this->unserializeTag()) {
                    $list[$data['n']] = $data;
                }
                return $list;
            case self::TAG_Int_Array:
                $size = $this->readInt();
                $data = array();
                for($i=0; $i<$size; $i++) {
                    $data[] = $this->readInt();
                }
                return $data;
            case self::TAG_Long_Array:
                $size = $this->readInt();
                $data = array();
                for($i=0; $i<$size; $i++) {
                    $data[] = $this->readLong();
                }
                return $data;
        }
        die("read NBT payload - unknown tag\n");
//        return null;    // hier sollte man nie ankommen ;-)
    }

    protected function writePayload ($typ, $data)
    {
        switch($typ) {
            case self::TAG_Byte:
                return $this->writeByte($data);
            case self::TAG_Short:
                return $this->writeShort($data);
            case self::TAG_Int:
                return $this->writeInt($data);
            case self::TAG_Long:
                return $this->writeLong($data);
            case self::TAG_Float:
                return $this->writeFloat($data);
            case self::TAG_Double:
                return $this->writeDouble($data);
            case self::TAG_Byte_Array:
                if(!is_array($data)) return false;
                $this->writeInt(count($data));
                foreach($data as $d) {
                    $this->writeByte($d);
                }
                return;
            case self::TAG_String:
                return $this->writeString($data);
            case self::TAG_List:
                $this->writeByte($data['t']);
                $len = count($data['d']);
                $this->writeInt($len);
                foreach($data['d'] as $d) {
                    $this->writePayload($data['t'], $d);
                }
                return;
            case self::TAG_Compound:
                foreach($data as $d) {
                    $this->serializeTag($d);
                }
                $this->writeByte(0);
                return;
            case self::TAG_Int_Array:
                if(!is_array($data)) return false;
                $this->writeInt(count($data));
                foreach($data as $d) {
                    $this->writeInt($d);
                }
                return;
            case self::TAG_Long_Array:
                if(!is_array($data)) return false;
                $this->writeInt(count($data));
                foreach($data as $d) {
                    $this->writeLong($d);
                }
                return;
        }
        return false;   // hier sollte man nie ankommen ;-)
    }

    protected function readByte ()
    {
        $data = $this->binary;
        return ord($data[$this->pos++]);
    }

    protected function writeByte ($data)
    {
        $this->binary .= chr($data & 0xFF);
        $this->pos++;
    }

    protected function readShort ()
    {
        $data = $this->binary;
        $off = $this->pos;
        $this->pos += 2;
        //return ord($data{$off}) << 8 | ord($data{$off+1});
        return unpack('n', substr($data, $off, 2))[1];  // big endian
    }

    protected function writeShort ($data)
    {
        $this->binary .= pack('n', $data & 0xFFFF);
        $this->pos += 2;
    }

    protected function readInt ($signed=false)
    {
        $data = $this->binary;
        $off = $this->pos;
        $this->pos += 4;
        //$val = ord($data{$off}) << 24 | ord($data{$off+1}) << 16 | ord($data{$off+2}) << 8 | ord($data{$off+3});
        $val = unpack('N', substr($data, $off, 4))[1];
        if($signed) {
            if($val & 0x80000000) {
                $val |= 0xFFFFFFFF00000000;
            }
        }
        return $val;
    }

    protected function writeInt ($data)
    {
        $this->binary .= pack('N', $data & 0xFFFFFFFF);
        $this->pos += 4;
    }

    protected function readLong ()
    {
        //$hi = $this->readInt();
        //$lo = $this->readInt();
        //return ($hi << 32) | $lo;
        $data = $this->binary;
        $off = $this->pos;
        $this->pos += 8;
        return unpack('J', substr($data, $off, 8))[1];  // big endian
    }

    protected function writeLong ($data)
    {
        $this->binary .= pack('J', $data);
        $this->pos += 8;
    }

    protected function readFloat ()
    {
        return $this->readInt();
        //$data = $this->binary;
        //$off = $this->pos;
        //$this->pos += 4;
        //return unpack('f', substr($data, $off, 4))[1];
    }

    protected function writeFloat ($data)
    {
        $this->writeInt($data);
        //$this->binary .= pack('f', $data & 0xFFFFFFFF);
        //$this->pos += 4;
    }

    protected function readDouble ()
    {
        return $this->readLong();
        //$data = $this->binary;
        //$off = $this->pos;
        //$this->pos += 8;
        //return unpack('d', substr($data, $off, 8))[1];
    }

    protected function writeDouble ($data)
    {
        $this->writeLong($data);
        //$this->binary .= pack('d', $data);
        //$this->pos += 8;
    } 

    protected function readString ($len)
    {
        $data = $this->binary;
        $off = $this->pos;
        $this->pos += $len;
        return substr($data, $off, $len);
    }

    protected function writeString ($data)
    {
        $len = strlen($data);
        $this->binary .= $this->writeShort($len);
        $this->binary .= $data;
        $this->pos += $len;
    }
}
